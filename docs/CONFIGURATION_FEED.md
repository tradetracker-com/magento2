# Feed Configuration

Complete reference for all product feed settings. The feed exports your product catalog to TradeTracker for affiliate marketing.

**Location:** Stores → Configuration → TradeTracker → Product Feed

---

## General

### Enable

Enable or disable feed generation for this store view. Each store view has its own feed.

### Filename

Custom filename for the generated feed. Leave default unless you have specific requirements.

### Auto Generate

Enable automatic feed generation via Magento cron.

**Frequency options:**
- Daily
- Every 12 hours
- Every 6 hours
- Every hour
- Custom (enter crontab format)

---

## Product Data

### General Fields

| Setting | Description |
|---------|-------------|
| **Name** | Product name attribute. Should match your landing page title. |
| **Short Description** | Brief product description |
| **Long Description** | Full product description |

### Image Source

| Setting | Description |
|---------|-------------|
| **Source** | Which images to export: Main only or All images |
| **Main Image** | When using "All images", select which becomes the primary image |

**Recommendation:** Select "All images" to include additional images in your feed.

### Additional Fields

| Setting | Description |
|---------|-------------|
| **EAN-code** | European Article Number (barcode) |
| **Brand** | Product brand/manufacturer |
| **Color** | Product color attribute |
| **Material** | Product material attribute |
| **Size** | Product size attribute |
| **Gender** | Target gender attribute |
| **Extra Info** | Static text added to all products (e.g., "Free pickup from store") |

### Category

| Setting | Options |
|---------|---------|
| **Type** | Magento categories, Custom value, or Product attribute |
| **Attribute** | When using attribute as source |
| **Category** | Custom category value or path |

**Category Override:** You can set category per product or category in:
- Catalog → Categories → TradeTracker Category
- Catalog → Products → TradeTracker Tab → TradeTracker Category

### Delivery

| Setting | Description |
|---------|-------------|
| **Type** | Static values or from attribute |
| **Attribute** | Use product attribute for delivery time |
| **Delivery Time - In Stock** | Static delivery time for in-stock products |
| **Delivery Time - Out of Stock** | Static delivery time for out-of-stock products |

---

## Product Types

### Configurable Products

| Setting | Recommended | Description |
|---------|-------------|-------------|
| **Use Configurable/Simple** | Simple products only | Export simples as individual products |
| **Use Parent URL** | Yes, with Auto-Link | Links to configurable page with correct variant |
| **Use Parent Image** | Only if Empty | Falls back to parent image when simple has none |
| **Use Parent Data** | Select attributes | Inherit selected attributes from parent |
| **Fallback on Non-Visible only** | Yes | Only apply fallback on "Not Visible Individually" simples |

### Bundle Products

| Setting | Recommended | Description |
|---------|-------------|-------------|
| **Use Bundle Products** | Parent only | Export the bundle, not individual components |
| **Use Parent URL** | Yes | Link to bundle page |
| **Use Parent Image** | Only if Empty | Falls back to parent image |

### Grouped Products

| Setting | Recommended | Description |
|---------|-------------|-------------|
| **Use Grouped Products** | Parent only | Export the grouped product |
| **Parent Price** | Min/Max/Sum | How to calculate price from children |

---

## Additional Configuration

### Extra Fields

Add custom fields to your feed using any product attribute. Use TradeTracker's field name standards.

### Shipping Prices

Configure shipping costs based on product price ranges:

| From Price | To Price | Shipping Cost |
|------------|----------|---------------|
| 0 | 50 | 4.95 |
| 50 | 100 | 2.95 |
| 100 | - | 0.00 |

### Google Analytics Tracking

Add UTM parameters to product URLs for tracking in Google Analytics.

**Example:** `utm_source=tradetracker&utm_medium=cpc&utm_campaign=tradetracker`

---

## Filter Options

### Filter on Visibility

Only include products with specific visibility settings.

### Filter by Category

Include or exclude products by category.

| Mode | Description |
|------|-------------|
| **Include** | Only products in selected categories |
| **Exclude** | All products except those in selected categories |

### Exclude Out of Stock

Remove out-of-stock products from the feed.

### Advanced Filters

Create custom filters using any product attribute with conditions like:
- Equals
- Not equals
- Contains
- Greater than
- Less than

---

## TradeTracker - Feeds

This section displays all generated feeds with their URLs. Use these URLs when configuring your feed in TradeTracker.

---

## CLI Command

Generate feed manually via command line:

```bash
bin/magento tradetracker:feed:generate
```

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-tradetracker-feed-and-pixel.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
