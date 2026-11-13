# Feed Requirements

Required and recommended product attributes for your [TradeTracker](https://www.magmodules.eu/magento2-tradetracker-feed-and-pixel.html) product feed. This guide explains what TradeTracker expects and how to configure each attribute in the extension.

---

## Required Attributes

These attributes must be present for TradeTracker to accept your products.

| Attribute | Description | Magento Mapping |
|-----------|-------------|-----------------|
| **ID** | Unique product identifier | SKU or Product ID |
| **name** | Product title | Product Name |
| **description** | Product description | Description or Short Description |
| **price** | Current selling price | Price (including special price if active) |
| **currency** | Currency code (EUR, USD, etc.) | Store currency |
| **productURL** | Link to product page | Generated automatically |
| **imageURL** | Main product image | Base Image |
| **categories** | Product category path | Category assignment |
| **brand** | Manufacturer/brand name | Manufacturer attribute |

---

## Recommended Attributes

These attributes improve product matching and feed quality.

| Attribute | Description | Why It Matters |
|-----------|-------------|----------------|
| **GTIN** | EAN/UPC barcode | Helps match products across publishers |
| **SKU** | Stock Keeping Unit | Alternative product identifier |
| **stock** | Inventory quantity or status | Shows availability to publishers |
| **discount** | Discount percentage or amount | Highlights deals and promotions |
| **size** | Product size | Essential for fashion/apparel |
| **color** | Product color | Essential for fashion/apparel |
| **gender** | Target gender | Improves targeting for fashion |
| **material** | Product material | Adds product detail |
| **subcategories** | Additional categorization | More precise product placement |
| **tags** | Product tags/keywords | Helps with product discovery |

---

## Configuring Attributes

In the extension configuration (Magmodules → TradeTracker → Feed Settings), you map Magento attributes to TradeTracker fields.

### Basic Mapping

| TradeTracker Field | Recommended Magento Attribute |
|--------------------|-------------------------------|
| Product Name | `name` |
| Description | `short_description` (cleaner output) |
| Brand | `manufacturer` |
| GTIN/EAN | `ean` or custom attribute |
| SKU | `sku` |
| Color | `color` |
| Size | `size` |

### Tips for Better Feed Quality

**Description:**
- Use Short Description for cleaner output (no HTML)
- Long descriptions may be truncated by publishers

**Brand:**
- Ensure all products have a brand assigned
- Create a default value for unbranded products

**GTIN/EAN:**
- Required for product matching across publishers
- Use 13-digit EAN or 12-digit UPC format

**Images:**
- Use high-quality images (minimum 500x500px)
- White or transparent backgrounds preferred
- Main product image should show the complete product

---

## Stock Information

TradeTracker uses stock data to show product availability:

| Stock Status | Feed Value | Result |
|--------------|------------|--------|
| In Stock | Quantity or "in stock" | Product shown |
| Out of Stock | 0 or "out of stock" | May be hidden by publishers |

**Recommendation:** Enable stock filtering in the feed to exclude out-of-stock products entirely.

---

## Categories

TradeTracker requires category information for proper product placement.

### Category Format

Categories are exported as a path:
```
Electronics > Smartphones > Apple
```

### Configuration

In Feed Settings → Categories:
- Select which Magento categories to include
- Map to TradeTracker's category structure if required
- Exclude categories that shouldn't appear in the feed

---

## Price Handling

### Regular vs Special Price

The extension automatically uses:
- **Special price** if active (within date range)
- **Regular price** if no special price is set

### Discount Calculation

When a special price is active:
- Original price is included as `fromPrice`
- Current price as `price`
- Discount percentage calculated automatically

---

## Common Issues

### Missing Required Attributes

**Problem:** Feed rejected due to missing fields.

**Solution:**
1. Check attribute mapping in configuration
2. Ensure attributes have values for all products
3. Set default values for empty attributes

### Brand Not Appearing

**Problem:** Brand field is empty in feed.

**Solution:**
1. Verify manufacturer attribute is assigned to products
2. Check attribute scope (store view vs global)
3. Set a fallback value in configuration

### Images Not Loading

**Problem:** Image URLs return 404 errors.

**Solution:**
1. Regenerate product images via CLI
2. Check media folder permissions
3. Verify image cache is built

---

## Validation

Before submitting your feed:

1. **Download sample feed** from the configuration
2. **Check required fields** are populated
3. **Verify URLs** are accessible
4. **Test images** load correctly
5. **Confirm prices** match your store

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-tradetracker-feed-and-pixel.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
