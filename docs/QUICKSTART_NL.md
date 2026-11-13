# Snelstartgids

Verbind je Magento winkel met TradeTracker in enkele minuten. Deze gids behandelt de conversion pixel setup en product feed configuratie.

---

## Vereisten

Voordat je begint:

- TradeTracker account
- Campaign ID van je TradeTracker account
- Product ID van je TradeTracker account

---

## Stap 1: Conversion Pixel Inschakelen

1. Ga naar **Stores → Configuration → TradeTracker → General**
2. In de **Conversion Pixel** sectie:
   - Zet **Enable** op **Yes**
   - Voer je **Campaign ID** in
   - Voer je **Product ID** in
3. Klik **Save Config**

---

## Stap 2: Product Feed Configureren

1. Ga naar **Stores → Configuration → TradeTracker → Product Feed**
2. In de **General** sectie:
   - Zet **Enable** op **Yes**
   - Configureer bestandsnaam (standaard is prima)
   - Stel auto generate frequentie in (aanbevolen: Daily)
3. In de **Product Data** sectie:
   - Koppel je product attributen (Name, Description, Brand, EAN)
4. Klik **Save Config**

---

## Stap 3: Genereer Je Eerste Feed

1. Klik **Generate Feed** knop in de configuratie
2. Of gebruik CLI:
   ```bash
   bin/magento tradetracker:feed:generate
   ```
3. Kopieer de feed URL uit de **TradeTracker - Feeds** sectie

---

## Stap 4: Feed Indienen bij TradeTracker

1. Log in op je TradeTracker account
2. Ga naar je campagne instellingen
3. Voeg de feed URL toe als je product feed bron
4. Stel update frequentie in om overeen te komen met je Magento cron schema

---

## Stap 5: Pixel Verifiëren

1. Plaats een testbestelling in je winkel
2. Controleer TradeTracker dashboard voor de conversie
3. Verifieer dat orderbedrag en commissie correct zijn

---

## Optioneel: DirectLinking

Als je TradeTracker accountmanager je heeft gevraagd DirectLinking in te stellen:

1. Ga naar **Stores → Configuration → TradeTracker → General**
2. In de **DirectLinking** sectie:
   - Zet **Enable** op **Yes**
   - Voer de **URL Key** in die je accountmanager heeft gegeven
3. Klik **Save Config**

---

## Klaar!

Je Magento winkel is nu verbonden met TradeTracker. Producten worden gesynchroniseerd via de feed en conversies worden getrackt via de pixel.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-tradetracker-feed-and-pixel.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
