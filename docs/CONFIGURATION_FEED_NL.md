# Feed Configuratie

Volledige referentie voor alle product feed instellingen. De feed exporteert je productcatalogus naar TradeTracker voor affiliate marketing.

**Locatie:** Stores → Configuration → TradeTracker → Product Feed

---

## General

### Enable

Schakel feed generatie in of uit voor deze storeview. Elke storeview heeft zijn eigen feed.

### Filename

Aangepaste bestandsnaam voor de gegenereerde feed. Laat standaard tenzij je specifieke vereisten hebt.

### Auto Generate

Schakel automatische feed generatie in via Magento cron.

**Frequentie opties:**
- Daily
- Every 12 hours
- Every 6 hours
- Every hour
- Custom (voer crontab formaat in)

---

## Product Data

### General Fields

| Instelling | Beschrijving |
|------------|--------------|
| **Name** | Product naam attribuut. Moet overeenkomen met je landingspagina titel. |
| **Short Description** | Korte product beschrijving |
| **Long Description** | Volledige product beschrijving |

### Image Source

| Instelling | Beschrijving |
|------------|--------------|
| **Source** | Welke afbeeldingen exporteren: Alleen main of Alle afbeeldingen |
| **Main Image** | Bij "All images", selecteer welke de primaire afbeelding wordt |

**Aanbeveling:** Selecteer "All images" om extra afbeeldingen in je feed op te nemen.

### Additional Fields

| Instelling | Beschrijving |
|------------|--------------|
| **EAN-code** | European Article Number (barcode) |
| **Brand** | Product merk/fabrikant |
| **Color** | Product kleur attribuut |
| **Material** | Product materiaal attribuut |
| **Size** | Product maat attribuut |
| **Gender** | Doelgroep geslacht attribuut |
| **Extra Info** | Statische tekst toegevoegd aan alle producten (bijv. "Gratis ophalen in winkel") |

### Category

| Instelling | Opties |
|------------|--------|
| **Type** | Magento categorieën, Aangepaste waarde, of Product attribuut |
| **Attribute** | Wanneer attribuut als bron wordt gebruikt |
| **Category** | Aangepaste categorie waarde of pad |

**Categorie Overschrijven:** Je kunt categorie per product of categorie instellen in:
- Catalog → Categories → TradeTracker Category
- Catalog → Products → TradeTracker Tab → TradeTracker Category

### Delivery

| Instelling | Beschrijving |
|------------|--------------|
| **Type** | Statische waarden of vanuit attribuut |
| **Attribute** | Gebruik product attribuut voor levertijd |
| **Delivery Time - In Stock** | Statische levertijd voor voorradige producten |
| **Delivery Time - Out of Stock** | Statische levertijd voor niet-voorradige producten |

---

## Product Types

### Configurable Products

| Instelling | Aanbevolen | Beschrijving |
|------------|------------|--------------|
| **Use Configurable/Simple** | Simple products only | Exporteer simples als individuele producten |
| **Use Parent URL** | Yes, with Auto-Link | Linkt naar configurable pagina met correcte variant |
| **Use Parent Image** | Only if Empty | Valt terug op parent afbeelding als simple geen heeft |
| **Use Parent Data** | Selecteer attributen | Erf geselecteerde attributen over van parent |
| **Fallback on Non-Visible only** | Yes | Pas fallback alleen toe op "Not Visible Individually" simples |

### Bundle Products

| Instelling | Aanbevolen | Beschrijving |
|------------|------------|--------------|
| **Use Bundle Products** | Parent only | Exporteer de bundle, niet individuele componenten |
| **Use Parent URL** | Yes | Link naar bundle pagina |
| **Use Parent Image** | Only if Empty | Valt terug op parent afbeelding |

### Grouped Products

| Instelling | Aanbevolen | Beschrijving |
|------------|------------|--------------|
| **Use Grouped Products** | Parent only | Exporteer het grouped product |
| **Parent Price** | Min/Max/Sum | Hoe prijs te berekenen van children |

---

## Additional Configuration

### Extra Fields

Voeg custom velden toe aan je feed met elk product attribuut. Gebruik TradeTracker's veldnaam standaarden.

### Shipping Prices

Configureer verzendkosten op basis van product prijsbereiken:

| Van Prijs | Tot Prijs | Verzendkosten |
|-----------|-----------|---------------|
| 0 | 50 | 4.95 |
| 50 | 100 | 2.95 |
| 100 | - | 0.00 |

### Google Analytics Tracking

Voeg UTM parameters toe aan product URLs voor tracking in Google Analytics.

**Voorbeeld:** `utm_source=tradetracker&utm_medium=cpc&utm_campaign=tradetracker`

---

## Filter Options

### Filter op Visibility

Alleen producten met specifieke visibility instellingen opnemen.

### Filter op Categorie

Neem producten op of sluit uit op basis van categorie.

| Modus | Beschrijving |
|-------|--------------|
| **Include** | Alleen producten in geselecteerde categorieën |
| **Exclude** | Alle producten behalve die in geselecteerde categorieën |

### Exclude Out of Stock

Verwijder niet-voorradige producten uit de feed.

### Advanced Filters

Maak custom filters met elk product attribuut met condities zoals:
- Equals
- Not equals
- Contains
- Greater than
- Less than

---

## TradeTracker - Feeds

Deze sectie toont alle gegenereerde feeds met hun URLs. Gebruik deze URLs bij het configureren van je feed in TradeTracker.

---

## CLI Commando

Genereer feed handmatig via command line:

```bash
bin/magento tradetracker:feed:generate
```

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-tradetracker-feed-and-pixel.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
