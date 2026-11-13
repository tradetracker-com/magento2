# General Configuration

Complete reference for [TradeTracker](https://www.magmodules.eu/magento2-tradetracker-feed-and-pixel.html) General settings including credentials, conversion pixel, DirectLinking, and debug options.

**Location:** Stores > Configuration > TradeTracker > General

---

## General

Basic module settings and TradeTracker account credentials.

### Version

Shows your installed module version. Click to check if updates are available.

### Enable

Master switch for the TradeTracker extension. Set to **Yes** to activate all functionality.

**When to use:** Keep enabled once you've completed initial setup. Only disable if you need to temporarily stop all TradeTracker tracking without uninstalling the module.

### Customer ID

Your TradeTracker Customer ID for API authentication.

**Where to find it:** Log in to TradeTracker > Account Settings > API Credentials

### Passphrase

Your TradeTracker API passphrase for authentication.

**Where to find it:** Same location as Customer ID in your TradeTracker account.

### Validate Credentials

Click to test your Customer ID and Passphrase combination. A success message confirms the connection works.

### Magento Integration Token

Displays the integration token used for TradeTracker to communicate with your Magento store. This is automatically generated.

---

## Conversion Pixel

The conversion pixel tracks completed orders and reports them to TradeTracker for commission calculation and analytics.

### Enable

Activate conversion pixel tracking on your store's order success page.

**When to enable:** After you've entered your Campaign ID and Product ID. The pixel will fire on every successful checkout.

### Pixel Settings

| Setting | Description |
|---------|-------------|
| **Campaign ID** | Your TradeTracker campaign identifier |
| **Product ID** | Your TradeTracker product identifier |

**Where to find these values:** Log in to your TradeTracker account and navigate to your campaign settings.

### Overriding Product ID

You can set different Product IDs at category or product level for different commission structures:

**Category Level:**
Catalog > Categories > Select Category > TradeTracker > Product ID

**Product Level:**
Catalog > Products > Select Product > TradeTracker > TradeTracker Product ID

**When to use:** When you have different commission tiers for different product categories (e.g., higher commission on accessories vs. main products).

### Advanced Settings

| Setting | Description |
|---------|-------------|
| **Overwrite Currency Code** | Enable to use a different currency than the store default |
| **Currency Code** | ISO 4217 currency code (e.g., EUR, USD, GBP) |

**Important:** Only change currency settings in consultation with your TradeTracker account manager. The currency must match what TradeTracker expects for your campaign.

---

## DirectLinking

DirectLinking allows affiliates to link directly to your products while ensuring proper tracking attribution.

### Enable

Activate the DirectLinking redirect functionality.

**When to enable:** Only when your TradeTracker account manager has instructed you to set up DirectLinking for your campaign.

### URL Key

The URL path segment for DirectLinking redirects.

**Example:** If you enter `offers`, affiliates can use `yourstore.com/offers?product=123`

**Important:**
- Use the URL key provided by your TradeTracker account manager
- Once your campaign is active, do not change this value
- If you need a different keyword, inform your account manager first

### How DirectLinking Works

1. Affiliate creates a link: `yourstore.com/offers?product=123`
2. Customer clicks the link
3. Extension captures tracking parameters and stores them in a cookie
4. Customer is redirected to the actual product page
5. Conversion is properly attributed to the affiliate when they purchase

---

## Advanced Setting

Additional tracking options for multi-country campaigns.

### Multi-country Tracking Group

Enable this if you run campaigns across multiple countries and need unified tracking.

**When to enable:** Only when your TradeTracker account manager has set up a tracking group for your multi-country campaigns.

### Tracking Group ID (TGI)

The numeric Tracking Group ID provided by TradeTracker.

**Note:** This field only appears when Multi-country Tracking Group is enabled. Enter the exact TGI number provided by your account manager.

---

## Debug

Troubleshooting tools for diagnosing issues.

### Debug Mode

Enable detailed logging for feed generation and conversion tracking.

**When to enable:** Only when troubleshooting issues. Disable in production for performance.

### Log Files

When debug mode is enabled, logs are written to:
- `var/log/tradetracker/debug.log` - General debug information
- `var/log/tradetracker/error.log` - Error messages

Click the **Download Log** button in the admin to retrieve log files directly.

### What Gets Logged

- Conversion pixel firing events
- Order data sent to TradeTracker
- DirectLinking redirect events
- API communication with TradeTracker
- Feed generation progress (if applicable)

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-tradetracker-feed-and-pixel.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
