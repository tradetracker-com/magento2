# Feed Vereisten

Vereiste en aanbevolen productattributen voor uw [TradeTracker](https://www.magmodules.nl/magento2-tradetracker-feed-and-pixel.html) productfeed. Deze gids legt uit wat TradeTracker verwacht en hoe u elk attribuut configureert in de extensie.

---

## Vereiste Attributen

Deze attributen moeten aanwezig zijn voordat TradeTracker uw producten accepteert.

| Attribuut | Beschrijving | Magento Mapping |
|-----------|--------------|-----------------|
| **ID** | Unieke product identifier | SKU of Product ID |
| **name** | Producttitel | Productnaam |
| **description** | Productomschrijving | Beschrijving of Korte Beschrijving |
| **price** | Huidige verkoopprijs | Prijs (inclusief actieprijs indien actief) |
| **currency** | Valutacode (EUR, USD, etc.) | Winkelvaluta |
| **productURL** | Link naar productpagina | Automatisch gegenereerd |
| **imageURL** | Hoofd productafbeelding | Basisafbeelding |
| **categories** | Product categoriepad | Categorietoewijzing |
| **brand** | Fabrikant/merknaam | Fabrikant attribuut |

---

## Aanbevolen Attributen

Deze attributen verbeteren productmatching en feedkwaliteit.

| Attribuut | Beschrijving | Waarom Het Belangrijk Is |
|-----------|--------------|--------------------------|
| **GTIN** | EAN/UPC barcode | Helpt producten te matchen bij publishers |
| **SKU** | Stock Keeping Unit | Alternatieve product identifier |
| **stock** | Voorraad hoeveelheid of status | Toont beschikbaarheid aan publishers |
| **discount** | Kortingspercentage of bedrag | Belicht deals en promoties |
| **size** | Productmaat | Essentieel voor mode/kleding |
| **color** | Productkleur | Essentieel voor mode/kleding |
| **gender** | Doelgroep geslacht | Verbetert targeting voor mode |
| **material** | Productmateriaal | Voegt productdetail toe |
| **subcategories** | Extra categorisering | Preciezere productplaatsing |
| **tags** | Product tags/trefwoorden | Helpt bij product vindbaarheid |

---

## Attributen Configureren

In de extensie configuratie (Magmodules → TradeTracker → Feed Instellingen) koppelt u Magento attributen aan TradeTracker velden.

### Basis Mapping

| TradeTracker Veld | Aanbevolen Magento Attribuut |
|-------------------|------------------------------|
| Productnaam | `name` |
| Beschrijving | `short_description` (schonere output) |
| Merk | `manufacturer` |
| GTIN/EAN | `ean` of aangepast attribuut |
| SKU | `sku` |
| Kleur | `color` |
| Maat | `size` |

### Tips voor Betere Feed Kwaliteit

**Beschrijving:**
- Gebruik Korte Beschrijving voor schonere output (geen HTML)
- Lange beschrijvingen kunnen worden afgekapt door publishers

**Merk:**
- Zorg dat alle producten een merk toegewezen hebben
- Maak een standaardwaarde voor producten zonder merk

**GTIN/EAN:**
- Vereist voor productmatching bij publishers
- Gebruik 13-cijferig EAN of 12-cijferig UPC formaat

**Afbeeldingen:**
- Gebruik hoogwaardige afbeeldingen (minimaal 500x500px)
- Witte of transparante achtergronden hebben de voorkeur
- Hoofdafbeelding moet het complete product tonen

---

## Voorraad Informatie

TradeTracker gebruikt voorraaddata om productbeschikbaarheid te tonen:

| Voorraadstatus | Feed Waarde | Resultaat |
|----------------|-------------|-----------|
| Op Voorraad | Hoeveelheid of "in stock" | Product getoond |
| Niet op Voorraad | 0 of "out of stock" | Kan verborgen worden door publishers |

**Aanbeveling:** Schakel voorraadfiltering in de feed in om niet-voorradige producten volledig uit te sluiten.

---

## Categorieën

TradeTracker vereist categorie-informatie voor juiste productplaatsing.

### Categorie Formaat

Categorieën worden geëxporteerd als een pad:
```
Elektronica > Smartphones > Apple
```

### Configuratie

In Feed Instellingen → Categorieën:
- Selecteer welke Magento categorieën u wilt opnemen
- Map naar TradeTracker's categoriestructuur indien nodig
- Sluit categorieën uit die niet in de feed moeten verschijnen

---

## Prijs Verwerking

### Reguliere vs Actieprijs

De extensie gebruikt automatisch:
- **Actieprijs** indien actief (binnen datumbereik)
- **Reguliere prijs** als er geen actieprijs is ingesteld

### Korting Berekening

Wanneer een actieprijs actief is:
- Originele prijs wordt opgenomen als `fromPrice`
- Huidige prijs als `price`
- Kortingspercentage automatisch berekend

---

## Veelvoorkomende Problemen

### Ontbrekende Vereiste Attributen

**Probleem:** Feed afgewezen vanwege ontbrekende velden.

**Oplossing:**
1. Controleer attribuut mapping in configuratie
2. Zorg dat attributen waarden hebben voor alle producten
3. Stel standaardwaarden in voor lege attributen

### Merk Verschijnt Niet

**Probleem:** Merkveld is leeg in feed.

**Oplossing:**
1. Verifieer dat fabrikant attribuut is toegewezen aan producten
2. Controleer attribuut scope (store view vs globaal)
3. Stel een fallback waarde in de configuratie in

### Afbeeldingen Laden Niet

**Probleem:** Afbeelding URLs geven 404 fouten.

**Oplossing:**
1. Regenereer productafbeeldingen via CLI
2. Controleer media map permissies
3. Verifieer dat image cache is opgebouwd

---

## Validatie

Voordat u uw feed indient:

1. **Download voorbeeld feed** vanuit de configuratie
2. **Controleer vereiste velden** zijn ingevuld
3. **Verifieer URLs** zijn toegankelijk
4. **Test afbeeldingen** laden correct
5. **Bevestig prijzen** komen overeen met uw winkel

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-tradetracker-feed-and-pixel.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
