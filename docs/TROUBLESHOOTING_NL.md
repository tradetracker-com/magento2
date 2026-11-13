# Probleemoplossing

Veelvoorkomende problemen en oplossingen voor de TradeTracker Feed & Pixel extensie.

---

## Snelle Diagnose

1. Controleer of module is ingeschakeld in zowel General als Product Feed secties
2. Wis Magento cache
3. Verifieer dat Campaign ID en Product ID correct zijn
4. Controleer feed generatie in TradeTracker - Feeds sectie
5. Bekijk logs: `var/log/`

---

## Pixel Problemen

### Conversies Worden Niet Getrackt

**Symptomen:**
- Orders verschijnen niet in TradeTracker dashboard
- Geen conversies geregistreerd

**Oplossingen:**

1. **Controleer pixel is ingeschakeld:**
   - Ga naar TradeTracker → General → Conversion Pixel
   - Verifieer Enable = Yes

2. **Verifieer credentials:**
   - Controleer of Campaign ID correct is
   - Controleer of Product ID correct is
   - Log in bij TradeTracker om waarden te verifiëren

3. **Controleer page source:**
   - Rond een testbestelling af
   - Bekijk source op success pagina
   - Zoek naar TradeTracker tracking code

4. **Wis caches:**
   - Full page cache
   - Browser cache

### Verkeerd Commissiebedrag

**Symptomen:**
- Conversies tracken maar bedragen zijn incorrect
- Valuta mismatch

**Oplossingen:**

1. **Controleer valuta instellingen:**
   - Verifieer dat valuta overeenkomt met TradeTracker verwachtingen
   - Check "Overwrite Currency Code" instelling

2. **Controleer order totalen:**
   - Verifieer wat er wordt verstuurd (subtotaal vs totaal)
   - Overleg met TradeTracker accountmanager

### DirectLinking Werkt Niet

**Symptomen:**
- Affiliate links redirecten niet correct
- Tracking parameters verloren

**Oplossingen:**

1. **Controleer DirectLinking is ingeschakeld:**
   - Ga naar TradeTracker → General → DirectLinking
   - Verifieer Enable = Yes

2. **Verifieer URL Key:**
   - Controleer of URL key overeenkomt met wat TradeTracker verwacht
   - Test de redirect URL handmatig

3. **Controleer op URL conflicten:**
   - Zorg dat geen andere extensie dezelfde URL key gebruikt
   - Check op CMS pagina conflicten

---

## Feed Problemen

### Feed Genereert Niet

**Symptomen:**
- Geen feed bestand aangemaakt
- Lege feed
- Generate knop doet niets

**Oplossingen:**

1. **Controleer feed is ingeschakeld:**
   - Ga naar TradeTracker → Product Feed → General
   - Verifieer Enable = Yes voor deze storeview

2. **Controleer cron:**
   - Verifieer dat Magento cron draait
   - Check cron_schedule tabel voor tradetracker jobs

3. **Genereer handmatig:**
   ```bash
   bin/magento tradetracker:feed:generate
   ```

4. **Bekijk logs:**
   - Review `var/log/` voor fouten
   - Kijk naar memory of timeout problemen

### Feed Mist Producten

**Symptomen:**
- Sommige producten niet in feed
- Product aantal lager dan verwacht

**Oplossingen:**

1. **Controleer filters:**
   - Review Filter Options instellingen
   - Check visibility filter
   - Check categorie filter
   - Check voorraad filter

2. **Controleer product data:**
   - Product moet ingeschakeld zijn
   - Product moet vereiste attributen hebben
   - Product moet aan filter criteria voldoen

3. **Controleer product types:**
   - Voor configurable: check of simples of parents worden geëxporteerd
   - Verifieer Product Types instellingen komen overeen met je behoeften

### Feed Heeft Verkeerde Data

**Symptomen:**
- Verkeerde prijzen
- Verkeerde afbeeldingen
- Ontbrekende beschrijvingen

**Oplossingen:**

1. **Controleer attribuut mapping:**
   - Verifieer dat elk veld naar correct Magento attribuut mapt
   - Check Name, Description, Brand, EAN mappings

2. **Controleer product type instellingen:**
   - Verifieer parent/simple fallback instellingen
   - Check "Use Parent Data" selecties

3. **Regenereer feed:**
   - Wis cache
   - Genereer nieuwe feed
   - Verifieer in TradeTracker - Feeds sectie

### Feed URL Werkt Niet

**Symptomen:**
- 404 fout bij benaderen feed URL
- Feed URL geeft leeg terug

**Oplossingen:**

1. **Controleer feed is gegenereerd:**
   - Ga naar TradeTracker - Feeds sectie
   - Verifieer dat feed bestand bestaat

2. **Controleer bestandspermissies:**
   - Feed bestanden moeten leesbaar zijn
   - Check `pub/media/` permissies

3. **Controleer webserver config:**
   - XML bestanden moeten correct geserveerd worden
   - Geen toegangsbeperkingen op feed directory

---

## Performance Problemen

### Feed Generatie Langzaam

**Oplossingen:**

1. **Draai tijdens daluren:**
   - Plan cron voor rustige uren
   - Genereer handmatig wanneer nodig

2. **Controleer product aantal:**
   - Grote catalogi duren langer
   - Overweeg filteren om producten te verminderen

3. **Controleer server resources:**
   - Verhoog PHP memory limit indien nodig
   - Check op timeout problemen

---

## Veelvoorkomende Foutmeldingen

| Fout | Oorzaak | Oplossing |
|------|---------|-----------|
| Invalid Campaign ID | Verkeerde of ontbrekende Campaign ID | Verifieer in TradeTracker account |
| Invalid Product ID | Verkeerde of ontbrekende Product ID | Verifieer in TradeTracker account |
| Feed generation failed | Geheugen of timeout | Verhoog limits of verminder producten |
| DirectLinking URL conflict | URL key al in gebruik | Wijzig URL key |
| No products in feed | Alle producten gefilterd | Review filter instellingen |

---

## Debug Modus

### Schakel Debug Logging In

1. Ga naar TradeTracker → General → Debug
2. Schakel Debug Mode in
3. Reproduceer het probleem
4. Check logs in `var/log/`

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-tradetracker-feed-and-pixel.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team

Bij contact met support, vermeld:
- Magento versie
- Module versie
- Foutmeldingen uit logs
- Screenshots van configuratie
- Stappen om te reproduceren
