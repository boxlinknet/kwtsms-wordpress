#!/usr/bin/env bash
# kwtSMS WordPress Plugin — Docker test environment full setup
# Run once after `docker compose up -d` and containers are healthy.
# Usage: bash docker/setup.sh

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP="docker compose -f ${SCRIPT_DIR}/docker-compose.yml --profile cli run --rm wpcli --allow-root"

# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------
step() { echo; echo "==> $*"; }

# ---------------------------------------------------------------------------
# Step 0: Wait for WordPress container + DB
# ---------------------------------------------------------------------------
step "Waiting for WordPress to be accessible..."
for i in $(seq 1 30); do
  if $WP core is-installed 2>/dev/null; then
    echo "   WordPress already installed."
    break
  fi
  if curl -sf http://localhost:8090/ > /dev/null 2>&1; then
    echo "   Container responding."
    break
  fi
  echo "   Attempt $i/30 — waiting 10s..."
  sleep 10
done

# ---------------------------------------------------------------------------
# Step 1: Install WordPress core
# ---------------------------------------------------------------------------
if ! $WP core is-installed 2>/dev/null; then
  step "Installing WordPress core..."
  $WP core install \
    --url="http://localhost:8090" \
    --title="kwtSMS Website" \
    --admin_user="admin" \
    --admin_password="Test@12345" \
    --admin_email="admin@kwtsms.test" \
    --skip-email
else
  step "WordPress already installed, skipping core install."
fi

# ---------------------------------------------------------------------------
# Step 2: Site options
# ---------------------------------------------------------------------------
step "Configuring site options..."
$WP option update blogname "kwtSMS Website"
$WP option update blogdescription "SMS OTP and Notifications — Test Environment"
$WP option update timezone_string "Asia/Kuwait"
$WP option update date_format "Y-m-d"
$WP option update time_format "H:i"
$WP option update start_of_week 0
$WP option update users_can_register 1
$WP option update default_role subscriber

# ---------------------------------------------------------------------------
# Step 3: Theme
# ---------------------------------------------------------------------------
step "Installing Sydney theme..."
$WP theme install sydney --activate 2>&1 || $WP theme activate sydney 2>/dev/null || echo "   Sydney install failed — using default theme"

# ---------------------------------------------------------------------------
# Step 4: Remove default plugins
# ---------------------------------------------------------------------------
step "Removing Hello Dolly and Akismet..."
$WP plugin delete hello akismet 2>/dev/null || true

# ---------------------------------------------------------------------------
# Step 5: Install plugins
# ---------------------------------------------------------------------------
step "Installing WooCommerce (activate) + CF7, WPForms, Elementor, Ninja Forms (install only)..."
$WP plugin install woocommerce --activate
$WP plugin install contact-form-7 2>/dev/null || true
$WP plugin install wpforms-lite 2>/dev/null || true
$WP plugin install elementor 2>/dev/null || true
$WP plugin install ninja-forms 2>/dev/null || true

# ---------------------------------------------------------------------------
# Step 6: Activate kwtsms
# ---------------------------------------------------------------------------
step "Activating kwtsms plugin..."
$WP plugin activate kwtsms

# Verify
$WP plugin list --status=active --fields=name

# ---------------------------------------------------------------------------
# Step 7: WooCommerce setup
# ---------------------------------------------------------------------------
step "Setting up WooCommerce pages and options..."
$WP wc tool run install_pages --user=admin 2>/dev/null || true
$WP option update woocommerce_store_address "Kuwait City"
$WP option update woocommerce_default_country "KW"
$WP option update woocommerce_currency "KWD"
$WP option update woocommerce_onboarding_profile '{"skipped":true}' --format=json 2>/dev/null || true

# WooCommerce sample products (uses built-in generator)
$WP wc product create \
  --name="Test T-Shirt" \
  --type=simple \
  --regular_price=8.500 \
  --user=admin 2>/dev/null || true

$WP wc product create \
  --name="Sample Mug" \
  --type=simple \
  --regular_price=3.000 \
  --user=admin 2>/dev/null || true

$WP wc product create \
  --name="Premium Bundle" \
  --type=simple \
  --regular_price=25.000 \
  --user=admin 2>/dev/null || true

# ---------------------------------------------------------------------------
# Step 8: kwtSMS credentials and settings
# ---------------------------------------------------------------------------
step "Configuring kwtSMS plugin settings..."

# API credentials (stored in nested option groups the plugin uses)
$WP option update kwtsms_gateway \
  '{"username":"YOUR_API_USERNAME","password":"YOUR_API_PASSWORD","sender_id":"","test_mode":"1"}' \
  --format=json 2>/dev/null || true

$WP option update kwtsms_general \
  '{"show_referral":"1","debug_logging":"1"}' \
  --format=json 2>/dev/null || true

# OTP plugin settings (stored under kwtsms_otp_general)
# Require OTP for all roles except administrator (excluded by default to prevent lockout).
$WP option update kwtsms_otp_general \
  '{"login_otp":1,"reset_otp":1,"otp_mode":"2fa","otp_required_roles":["editor","author","contributor","subscriber","customer","shop_manager"],"otp_length":6,"otp_expiry":5,"max_attempts":3,"debug_logging":1}' \
  --format=json 2>/dev/null || true

# Admin event alerts (keys must match DEFAULTS in class-kwtsms-settings.php).
$WP option update kwtsms_otp_alerts \
  '{"admin_phones":"96598765432","user_register":1,"wp_login":0,"post_published":1,"comment_posted":1,"core_update":1}' \
  --format=json 2>/dev/null || true

# ---------------------------------------------------------------------------
# Step 9: Test users (all password Test@12345)
# ---------------------------------------------------------------------------
step "Creating test users..."
create_user() {
  local login=$1 email=$2 role=$3 phone=$4
  $WP user create "$login" "$email" \
    --role="$role" \
    --user_pass="Test@12345" \
    --display_name="$(echo "$login" | sed 's/[0-9]*$//' | awk '{print toupper(substr($0,1,1)) substr($0,2)}')" \
    2>/dev/null || echo "   $login already exists"
  local uid
  uid=$($WP user get "$login" --field=ID 2>/dev/null) || true
  if [ -n "$uid" ] && [ -n "$phone" ]; then
    $WP user meta update "$uid" kwtsms_phone "$phone" 2>/dev/null || true
  fi
}

create_user subscriber1   subscriber1@test.com   subscriber    96599100001
create_user contributor1  contributor1@test.com  contributor   96599100002
create_user author1       author1@test.com       author        96599100003
create_user editor1       editor1@test.com       editor        96599100004
create_user shopmanager1  shopmanager1@test.com  shop_manager  96599100005
create_user customer1     customer1@test.com     customer      96598765432
create_user admin2        admin2@test.com        administrator 96599100007

# Set phone on primary admin (keeps admin out of the "Users Without Phone" list)
ADMIN_ID=$($WP user get admin --field=ID 2>/dev/null) || true
if [ -n "$ADMIN_ID" ]; then
  $WP user meta update "$ADMIN_ID" kwtsms_phone "96599100000" 2>/dev/null || true
fi

# Users WITHOUT phone numbers — for testing the "Users Without Phone" admin page.
# These users are in OTP-required roles but have no phone saved, so they will
# appear in the admin page and bypass OTP until a phone is assigned.
create_user nophone_sub   nophone.sub@test.com   subscriber    ""
create_user nophone_edit  nophone.edit@test.com  editor        ""
create_user nophone_cust  nophone.cust@test.com  customer      ""
create_user nophone_auth  nophone.auth@test.com  author        ""
# Admin without phone — appears on the page only when the administrator role
# is checked in "Require OTP for" (unchecked by default to prevent lockout).
create_user nophone_admin nophone.admin@test.com administrator ""

# ---------------------------------------------------------------------------
# Step 10: Mock SMS history log (500 records)
# ---------------------------------------------------------------------------
step "Generating 500 mock SMS history entries..."
$WP eval '
$phones   = ["96598765432","96598123456","96597654321","96598765432","96599987654","966501234567","971501234567","97455123456","96897654321"];
$types    = ["login","login","login","reset","reset","passwordless","passwordless","welcome","test"];
$statuses = ["sent","sent","sent","sent","sent","sent","failed","failed","failed"];
$senders  = ["kwtSMS","KWTSMS","InfoSMS"];
$codes    = ["ERR001","ERR002","ERR003","ERR004","ERR005"];

$entries = [];
$now = time();
for ( $i = 0; $i < 500; $i++ ) {
    $status  = $statuses[ array_rand( $statuses ) ];
    $type    = $types[ array_rand( $types ) ];
    $otp     = rand( 100000, 999999 );
    $failed  = "failed" === $status;
    $msgs    = [
        "Your OTP code is {$otp}. Valid for 10 minutes.",
        "Use {$otp} to log in to kwtSMS Website.",
        "Password reset OTP: {$otp}. Expires in 10 min.",
        "Welcome to kwtSMS Website! Your account is ready.",
        "Test SMS from kwtSMS Website.",
    ];
    $entries[] = [
        "time"        => $now - rand( 0, 9 ) * 86400 - rand( 0, 86399 ),
        "phone"       => $phones[ array_rand( $phones ) ],
        "message"     => $msgs[ array_rand( $msgs ) ],
        "status"      => $status,
        "type"        => $type,
        "msg_id"      => $failed ? "" : "MSG" . rand( 1000000, 9999999 ),
        "sender_id"   => $senders[ array_rand( $senders ) ],
        "api_username"=> "YOUR_API_USERNAME",
        "gateway_result" => [
            "ok"      => ! $failed,
            "code"    => $failed ? $codes[ array_rand( $codes ) ] : "",
            "message" => $failed ? "Delivery failed" : "",
        ],
    ];
}
usort( $entries, fn( $a, $b ) => $b["time"] - $a["time"] );
update_option( "kwtsms_otp_sms_history", $entries, false );
echo count( $entries ) . " SMS history entries created.\n";
'

# ---------------------------------------------------------------------------
# Step 11: Mock OTP attempt log (200 records)
# ---------------------------------------------------------------------------
step "Generating 200 mock OTP attempt entries..."
$WP eval '
$phones  = ["96598765432","96598123456","96597654321","96598765432","96599987654","966501234567","971501234567","97455123456","96897654321"];
$ips     = ["185.220.101.1","185.220.101.2","45.33.32.156","198.51.100.42","203.0.113.17","91.108.4.1","94.102.49.5","77.247.181.162","185.220.101.3"];
$actions = ["login","login","login","passwordless","passwordless","reset"];
$results = ["success","success","success","success","success","wrong_code","wrong_code","wrong_code","expired","expired","locked","locked","rate_limited","rate_limited","rate_limited","invalid_input"];
$users   = [1,2,3,4,5,null,null,null];

$entries = [];
$now = time();
for ( $i = 0; $i < 200; $i++ ) {
    $entries[] = [
        "time"    => $now - rand( 0, 9 ) * 86400 - rand( 0, 86399 ),
        "user_id" => $users[ array_rand( $users ) ],
        "phone"   => $phones[ array_rand( $phones ) ],
        "ip"      => $ips[ array_rand( $ips ) ],
        "action"  => $actions[ array_rand( $actions ) ],
        "result"  => $results[ array_rand( $results ) ],
    ];
}
usort( $entries, fn( $a, $b ) => $b["time"] - $a["time"] );
update_option( "kwtsms_otp_attempt_log", $entries, false );
echo count( $entries ) . " OTP attempt entries created.\n";
'

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
step "Setup complete!"
echo ""
echo "  WordPress:  http://localhost:8090"
echo "  Admin:      http://localhost:8090/wp-admin"
echo "  Username:   admin"
echo "  Password:   Test@12345"
echo ""
echo "  Test users (all password Test@12345):"
echo "    subscriber1, contributor1, author1, editor1,"
echo "    shopmanager1, customer1, admin2"
echo ""
echo "  kwtSMS API: username=YOUR_API_USERNAME (test mode ON)"
echo ""
