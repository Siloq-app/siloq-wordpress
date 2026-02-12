#!/bin/bash
# Setup WordPress Plugin API Key
# This script helps you generate API credentials for the WordPress plugin

set -e

echo "======================================"
echo "Siloq WordPress Plugin API Setup"
echo "======================================"
echo ""

# Get API URL
read -p "Enter Siloq API URL [http://localhost:3000/api/v1]: " API_URL
API_URL=${API_URL:-http://localhost:3000/api/v1}

# Get auth token (user needs to login first)
echo ""
echo "You need a JWT token to create API keys."
echo "To get one, login via the Siloq dashboard or API:"
echo ""
echo "  curl -X POST $API_URL/auth/login \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d '{\"email\": \"your@email.com\", \"password\": \"yourpass\"}'"
echo ""
read -p "Enter your JWT token: " AUTH_TOKEN

if [ -z "$AUTH_TOKEN" ]; then
    echo "Error: JWT token is required"
    exit 1
fi

# Check if user has a site or needs to create one
echo ""
read -p "Do you already have a Site ID? (y/n): " HAS_SITE

if [ "$HAS_SITE" = "n" ] || [ "$HAS_SITE" = "N" ]; then
    echo ""
    echo "Creating a new site..."
    read -p "WordPress URL: " WP_URL
    read -p "Site Name: " SITE_NAME

    SITE_RESPONSE=$(curl -s -X POST "$API_URL/sites" \
      -H "Authorization: Bearer $AUTH_TOKEN" \
      -H "Content-Type: application/json" \
      -d "{
        \"url\": \"$WP_URL\",
        \"name\": \"$SITE_NAME\",
        \"type\": \"wordpress\"
      }")

    SITE_ID=$(echo "$SITE_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

    if [ -z "$SITE_ID" ]; then
        echo "Error creating site. Response:"
        echo "$SITE_RESPONSE"
        exit 1
    fi

    echo "✅ Site created! Site ID: $SITE_ID"
else
    read -p "Enter your Site ID (UUID): " SITE_ID
fi

# Generate API key
echo ""
echo "Generating API key..."

API_KEY_RESPONSE=$(curl -s -X POST "$API_URL/api-keys" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"site_id\": \"$SITE_ID\",
    \"name\": \"WordPress Plugin\",
    \"scopes\": [\"read\", \"write\"],
    \"expires_in_days\": null
  }")

API_KEY=$(echo "$API_KEY_RESPONSE" | grep -o '"api_key":"sk-[^"]*"' | cut -d'"' -f4)

if [ -z "$API_KEY" ]; then
    echo "Error generating API key. Response:"
    echo "$API_KEY_RESPONSE"
    exit 1
fi

# Display results
echo ""
echo "======================================"
echo "✅ Setup Complete!"
echo "======================================"
echo ""
echo "WordPress Plugin Settings:"
echo ""
echo "API URL:"
echo "$API_URL"
echo ""
echo "API Key:"
echo "$API_KEY"
echo ""
echo "⚠️  IMPORTANT: Save this API key securely!"
echo "    It will not be shown again."
echo ""
echo "======================================"
echo ""

# Optionally save to file
read -p "Save credentials to .env file? (y/n): " SAVE_FILE

if [ "$SAVE_FILE" = "y" ] || [ "$SAVE_FILE" = "Y" ]; then
    ENV_FILE="wordpress-api-credentials.env"
    cat > "$ENV_FILE" << EOL
# Siloq WordPress Plugin Credentials
# Generated: $(date)

SILOQ_API_URL=$API_URL
SILOQ_API_KEY=$API_KEY
SILOQ_SITE_ID=$SITE_ID
EOL
    echo "✅ Credentials saved to: $ENV_FILE"
    echo ""
fi

echo "Next steps:"
echo "1. Go to your WordPress admin: Siloq → Settings"
echo "2. Enter the API URL and API Key above"
echo "3. Click 'Save Settings'"
echo "4. Click 'Test Connection' to verify"
echo ""
