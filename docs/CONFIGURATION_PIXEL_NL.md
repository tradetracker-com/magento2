# Algemene Configuratie

Volledige referentie voor [TradeTracker](https://www.magmodules.nl/magento2-tradetracker-feed-and-pixel.html) Algemene instellingen inclusief credentials, conversion pixel, DirectLinking en debug opties.

**Locatie:** Winkels > Configuratie > TradeTracker > General

---

## General

Basis module instellingen en TradeTracker account credentials.

### Version

Toont je geïnstalleerde module versie. Klik om te controleren of er updates beschikbaar zijn.

### Enable

Hoofdschakelaar voor de TradeTracker extensie. Zet op **Yes** om alle functionaliteit te activeren.

**Wanneer gebruiken:** Houd ingeschakeld zodra je de initiële setup hebt voltooid. Schakel alleen uit als je tijdelijk alle TradeTracker tracking wilt stoppen zonder de module te deïnstalleren.

### Customer ID

Je TradeTracker Customer ID voor API authenticatie.

**Waar te vinden:** Log in op TradeTracker > Account Settings > API Credentials

### Passphrase

Je TradeTracker API passphrase voor authenticatie.

**Waar te vinden:** Dezelfde locatie als Customer ID in je TradeTracker account.

### Validate Credentials

Klik om je Customer ID en Passphrase combinatie te testen. Een succesmelding bevestigt dat de verbinding werkt.

### Magento Integration Token

Toont het integratie token dat TradeTracker gebruikt om met je Magento winkel te communiceren. Dit wordt automatisch gegenereerd.

---

## Conversion Pixel

De conversion pixel trackt voltooide orders en rapporteert deze aan TradeTracker voor commissieberekening en analytics.

### Enable

Activeer conversion pixel tracking op de order success pagina van je winkel.

**Wanneer inschakelen:** Nadat je je Campaign ID en Product ID hebt ingevoerd. De pixel wordt afgevuurd bij elke succesvolle checkout.

### Pixel Instellingen

| Instelling | Beschrijving |
|------------|--------------|
| **Campaign ID** | Je TradeTracker campagne identifier |
| **Product ID** | Je TradeTracker product identifier |

**Waar vind je deze waarden:** Log in op je TradeTracker account en navigeer naar je campagne instellingen.

### Product ID Overschrijven

Je kunt verschillende Product IDs instellen op categorie of product niveau voor verschillende commissiestructuren:

**Categorie Niveau:**
Catalog > Categories > Selecteer Categorie > TradeTracker > Product ID

**Product Niveau:**
Catalog > Products > Selecteer Product > TradeTracker > TradeTracker Product ID

**Wanneer gebruiken:** Wanneer je verschillende commissie niveaus hebt voor verschillende productcategorieën (bijv. hogere commissie op accessoires vs. hoofdproducten).

### Geavanceerde Instellingen

| Instelling | Beschrijving |
|------------|--------------|
| **Overwrite Currency Code** | Inschakelen om een andere valuta te gebruiken dan de winkel standaard |
| **Currency Code** | ISO 4217 valutacode (bijv. EUR, USD, GBP) |

**Belangrijk:** Wijzig valuta instellingen alleen in overleg met je TradeTracker accountmanager. De valuta moet overeenkomen met wat TradeTracker verwacht voor je campagne.

---

## DirectLinking

DirectLinking stelt affiliates in staat om direct naar je producten te linken terwijl correcte tracking attributie wordt gegarandeerd.

### Enable

Activeer de DirectLinking redirect functionaliteit.

**Wanneer inschakelen:** Alleen wanneer je TradeTracker accountmanager je heeft geïnstrueerd om DirectLinking in te stellen voor je campagne.

### URL Key

Het URL pad segment voor DirectLinking redirects.

**Voorbeeld:** Als je `offers` invoert, kunnen affiliates `jouwwinkel.com/offers?product=123` gebruiken

**Belangrijk:**
- Gebruik de URL key die je TradeTracker accountmanager heeft gegeven
- Wijzig deze waarde niet zodra je campagne actief is
- Als je een ander keyword nodig hebt, informeer eerst je accountmanager

### Hoe DirectLinking Werkt

1. Affiliate maakt een link: `jouwwinkel.com/offers?product=123`
2. Klant klikt op de link
3. Extensie vangt tracking parameters en slaat ze op in een cookie
4. Klant wordt doorgestuurd naar de werkelijke productpagina
5. Conversie wordt correct toegekend aan de affiliate wanneer ze kopen

---

## Advanced Setting

Extra tracking opties voor multi-country campagnes.

### Multi-country Tracking Group

Schakel dit in als je campagnes over meerdere landen voert en uniforme tracking nodig hebt.

**Wanneer inschakelen:** Alleen wanneer je TradeTracker accountmanager een tracking groep heeft ingesteld voor je multi-country campagnes.

### Tracking Group ID (TGI)

Het numerieke Tracking Group ID dat TradeTracker heeft verstrekt.

**Let op:** Dit veld verschijnt alleen wanneer Multi-country Tracking Group is ingeschakeld. Voer het exacte TGI nummer in dat je accountmanager heeft gegeven.

---

## Debug

Troubleshooting tools voor het diagnosticeren van problemen.

### Debug Mode

Schakel gedetailleerde logging in voor feed generatie en conversion tracking.

**Wanneer inschakelen:** Alleen bij het oplossen van problemen. Schakel uit in productie voor betere prestaties.

### Log Bestanden

Wanneer debug mode is ingeschakeld, worden logs geschreven naar:
- `var/log/tradetracker/debug.log` - Algemene debug informatie
- `var/log/tradetracker/error.log` - Foutmeldingen

Klik op de **Download Log** knop in de admin om log bestanden direct op te halen.

### Wat Wordt Gelogd

- Conversion pixel firing events
- Order data verzonden naar TradeTracker
- DirectLinking redirect events
- API communicatie met TradeTracker
- Feed generatie voortgang (indien van toepassing)

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-tradetracker-feed-and-pixel.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
