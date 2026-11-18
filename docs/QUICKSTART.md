# Quick Start Guide

Get your Magento store connected to TradeTracker in minutes. This guide covers the conversion pixel setup and product feed configuration.

---

## Prerequisites

Before you begin:

- TradeTracker account
- Campaign ID from your TradeTracker account
- Product ID from your TradeTracker account

---

## Step 1: Enable Conversion Pixel

1. Go to **Stores → Configuration → TradeTracker → General**
2. In the **Conversion Pixel** section:
   - Set **Enable** to **Yes**
   - Enter your **Campaign ID**
   - Enter your **Product ID**
3. Click **Save Config**

---

## Step 2: Configure Product Feed

1. Go to **Stores → Configuration → TradeTracker → Product Feed**
2. In the **General** section:
   - Set **Enable** to **Yes**
   - Configure filename (default is fine)
   - Set auto generate frequency (recommended: Daily)
3. In the **Product Data** section:
   - Map your product attributes (Name, Description, Brand, EAN)
4. Click **Save Config**

---

## Step 3: Generate Your First Feed

1. Click **Generate Feed** button in the configuration
2. Or use CLI:
   ```bash
   bin/magento tradetracker:feed:generate
   ```
3. Copy the feed URL from the **TradeTracker - Feeds** section

---

## Step 4: Submit Feed to TradeTracker

1. Log in to your TradeTracker account
2. Go to your campaign settings
3. Add the feed URL as your product feed source
4. Set update frequency to match your Magento cron schedule

---

## Step 5: Verify Pixel

1. Place a test order in your store
2. Check TradeTracker dashboard for the conversion
3. Verify order amount and commission are correct

---

## Optional: DirectLinking

If your TradeTracker account manager has instructed you to set up DirectLinking:

1. Go to **Stores → Configuration → TradeTracker → General**
2. In the **DirectLinking** section:
   - Set **Enable** to **Yes**
   - Enter the **URL Key** provided by your account manager
3. Click **Save Config**

---

## You're Done!

Your Magento store is now connected to TradeTracker. Products sync via the feed, and conversions are tracked through the pixel.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-tradetracker-feed-and-pixel.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
