# Troubleshooting

Common issues and solutions for the TradeTracker Feed & Pixel extension.

---

## Quick Diagnostics

1. Check module is enabled in both General and Product Feed sections
2. Clear Magento cache
3. Verify Campaign ID and Product ID are correct
4. Check feed generation in TradeTracker - Feeds section
5. Check logs: `var/log/`

---

## Pixel Issues

### Conversions Not Tracking

**Symptoms:**
- Orders not appearing in TradeTracker dashboard
- No conversions recorded

**Solutions:**

1. **Check pixel is enabled:**
   - Go to TradeTracker → General → Conversion Pixel
   - Verify Enable = Yes

2. **Verify credentials:**
   - Check Campaign ID is correct
   - Check Product ID is correct
   - Log in to TradeTracker to verify values

3. **Check page source:**
   - Complete a test order
   - View source on success page
   - Search for TradeTracker tracking code

4. **Clear caches:**
   - Full page cache
   - Browser cache

### Wrong Commission Amount

**Symptoms:**
- Conversions track but amounts are incorrect
- Currency mismatch

**Solutions:**

1. **Check currency settings:**
   - Verify currency matches TradeTracker expectations
   - Check "Overwrite Currency Code" setting

2. **Check order totals:**
   - Verify what's being sent (subtotal vs grand total)
   - Consult with TradeTracker account manager

### DirectLinking Not Working

**Symptoms:**
- Affiliate links don't redirect properly
- Tracking parameters lost

**Solutions:**

1. **Check DirectLinking is enabled:**
   - Go to TradeTracker → General → DirectLinking
   - Verify Enable = Yes

2. **Verify URL Key:**
   - Check URL key matches what TradeTracker expects
   - Test the redirect URL manually

3. **Check for URL conflicts:**
   - Ensure no other extension uses the same URL key
   - Check for CMS page conflicts

---

## Feed Issues

### Feed Not Generating

**Symptoms:**
- No feed file created
- Empty feed
- Generate button does nothing

**Solutions:**

1. **Check feed is enabled:**
   - Go to TradeTracker → Product Feed → General
   - Verify Enable = Yes for this store view

2. **Check cron:**
   - Verify Magento cron is running
   - Check cron_schedule table for tradetracker jobs

3. **Generate manually:**
   ```bash
   bin/magento tradetracker:feed:generate
   ```

4. **Check logs:**
   - Review `var/log/` for errors
   - Look for memory or timeout issues

### Feed Has Missing Products

**Symptoms:**
- Some products not in feed
- Product count lower than expected

**Solutions:**

1. **Check filters:**
   - Review Filter Options settings
   - Check visibility filter
   - Check category filter
   - Check stock filter

2. **Check product data:**
   - Product must be enabled
   - Product must have required attributes
   - Product must meet filter criteria

3. **Check product types:**
   - For configurable: check if simples or parents are exported
   - Verify Product Types settings match your needs

### Feed Has Wrong Data

**Symptoms:**
- Wrong prices
- Wrong images
- Missing descriptions

**Solutions:**

1. **Check attribute mapping:**
   - Verify each field maps to correct Magento attribute
   - Check Name, Description, Brand, EAN mappings

2. **Check product type settings:**
   - Verify parent/simple fallback settings
   - Check "Use Parent Data" selections

3. **Regenerate feed:**
   - Clear cache
   - Generate new feed
   - Verify in TradeTracker - Feeds section

### Feed URL Not Working

**Symptoms:**
- 404 error when accessing feed URL
- Feed URL returns empty

**Solutions:**

1. **Check feed was generated:**
   - Go to TradeTracker - Feeds section
   - Verify feed file exists

2. **Check file permissions:**
   - Feed files should be readable
   - Check `pub/media/` permissions

3. **Check web server config:**
   - XML files should be served correctly
   - No access restrictions on feed directory

---

## Performance Issues

### Feed Generation Slow

**Solutions:**

1. **Run during off-peak:**
   - Schedule cron for low-traffic hours
   - Generate manually when needed

2. **Check product count:**
   - Large catalogs take longer
   - Consider filtering to reduce products

3. **Check server resources:**
   - Increase PHP memory limit if needed
   - Check for timeout issues

---

## Common Error Messages

| Error | Cause | Solution |
|-------|-------|----------|
| Invalid Campaign ID | Wrong or missing Campaign ID | Verify in TradeTracker account |
| Invalid Product ID | Wrong or missing Product ID | Verify in TradeTracker account |
| Feed generation failed | Memory or timeout | Increase limits or reduce products |
| DirectLinking URL conflict | URL key already used | Change URL key |
| No products in feed | All products filtered | Review filter settings |

---

## Debug Mode

### Enable Debug Logging

1. Go to TradeTracker → General → Debug
2. Enable Debug Mode
3. Reproduce the issue
4. Check logs in `var/log/`

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-tradetracker-feed-and-pixel.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team

When contacting support, provide:
- Magento version
- Module version
- Error messages from logs
- Screenshots of configuration
- Steps to reproduce
