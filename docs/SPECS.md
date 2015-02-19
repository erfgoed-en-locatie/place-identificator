# FRONTEND PiD - PlaceIdentificator
 

TODO
- [ ] uitzoeken waarom de homepage en adnere uitgezonderde pagina's geen app['user'] heeft?


## SPECIFICATIES

- user identificeerbaar aan emailadres, of iets met unieke bitly code
- dataset of csv met tenminste 1 veld: plaatsnaam, meer is mogelijk 

## MAPPING INTERFACE

### Scherm 1
upload csv

### Scherm 2
- resultaat van upload / import laten zien: velden benoemen
    - geoorloofd type velden: plaatsnaam + provincie 
    (omdat dit de enige contexten zijn waar we nu al wat mee kunnen)
    - periode: from + until (exact of ongeveer??) 
    - context velden : lat, lon: eventueel later :om mee te kunnen vergelijken, om evt te kunne bepalen of een gevonden treffer erbij in de buurt ligt
- metadata over de set: periode doen we dit per record.

- na de mapping moet het systeem de dataset valideren: bv op uniciteit van de plaatsnamen, 
als er provincies in staan of het de juiste benameingen (of afkortingen zijn) (lijstje met iso-codes standaarden opnemen oid??)
 
### Scherm 3
- context aangeven waarin je wilt dat de geocoder zoekt: geonames, of 1800 - 1900, of alleen provincie Friesland etc 
- aanvink vakjes voor welke datastes je wilt gebruiken om te standaardiseren - geonamen ja/nee, gg ja/nee
- keuze opties voor datasets die je teruggeleverd wilt hebben:
    -- geonames
    -- TGN
    -- BAG
    -- gemeentegeschiedenis
    -- pleiades? verdwenen dorpen? En waarom deze niet?? Wat worden de keuzes? Het verdewenen dorp Dorestad krijg je nu niet terug...
-- keuze opties voor het soort PiTs, dat je wilt zoeken: place of municipality (vvorlopig)
    -- scherm moet zo slim zijn dat als je municiplaity kiest, gg aangevinkt moet zijn (of andersom)

Test -> Scherm 4
Standaardiseer -> Scherm 5

### Scherm 4
- Toont testresulaten + rapportage, 9 gevonden etc.. Tevreden met test? Klik Standaardiseer -> Scherm 5

### Scherm 5
- Vat de boel nog even 
doet de mapping op basis van criteria en zegt dat de gebruiker gemaild wordt als het klaar is.


## MIJN DATASETS INTERFACE
### Scherm 1 Overzicht sets
- simpel overzicht van alle datasets behorend bij 1 user (inloggen met email + basic auth)
- sorteren op datum, en tonen al gemapped of niet
- set moet te deleten zijn
- datasets worden 4 weken bewaard

### Scherm 2 Dataset details
- 4 tabs: 
    - *Gestandaardiseerd*: overzicht van gemapte resultaat (alle records dus) + verwijder mapping knop (voor dit record) - gepagineerd
        -- welke data dan wel velden opslaan dan wel teruggeven: 
            --- oorspronkelijke plaatsnaam, geonames { 'label': bla, 'uri': 'geonames/12345', 'lat': 1232324, 'lon': 122334}, TGN, BAG, Gemeentegeschiedenis, (huidige) provincie
            --- extra veld erfgeo: voor het kunnen opslaan van "Dorestad"
            --- meerdere resultaten gevonden: dan slaan we precies hetzelfde op in een koppeltabel> id, dataset_id, record_id, rest idem
    
    - *Meerdere resultaten*: overzicht van de termen die meer dan 1 treffer hebben opgeleverd
        - optioneel: kort lijstje met overzicht van originale plaats -> en x opties gevonden -> knop bewerk/ "bepaal mapping"
        - Pagina met 1 term en alle gevonden mappings: met kaart en treffers, aanklikbaar zodat je maping kunt maken, en knop "weet niet" + "overslaan"
    - *Geen resultaat*: gepagineerd overzicht van alle niet gevonden termen en de regels waarmee ze niet gevonden zijn
        - hier tonen we opties om "fuzzy" oid te zoeken, en dan alleen voor deze subset (dus bijhouden hoe de resultaten gemapped zijn; EXACT / FUZZY), en later ook: tegen welke set
        - en dan ga je terug naar het overzicht van deze set
        - andere mogelijkheid: per record zelf een uri invullen -> klink "Bewaar"
        - andere mogelijkheid 2: per records de term waarop gezocht moet worden aanpassen -> klik "Zoek" (voor als we tijd over hebben)
        - Later kunnen we dit deel uitbreiden met zoeken in geonames etc...
    - *Download*: download opties, zoals
        - alle records, alleen gestandaardiseerde records, alleen met geonames, alleen met TGN, verschillende formaten


### Tabellen 
datasets
    - id, user_id, naam, file
    
records
    - id, dataset_id, original_name, geonames, tgn, bag, gg, erfgeo, createdAt etc

multiples
    - id, record_id, original_name, geonames, tgn, bag, gg, erfgeo, createdAt etc
    
users
    - id, email, code of ww of zoiets


Statussen van een Dataset:
Een dataset doorloopt een aantal verschillende stadia: van nieuw => velden gemapped => being mapped => mapping done 
Velden benoemen kun je niet meer aanpassen als dat al een keer gedaan is


# Vragen / todo lijst
- [] Slim oorspronkelijke namen in de bron oplossen? Stel de bron bevat "Voorschoten bij de Hage"? (Zoeken op deel van het woord in de bron?)



# Zoekacties geocoder (bespreken met Bert en Rutger)
- echt fuzzy zoeken? Niet alleen begint met en eindigt op. Nog meer opties?
    -- naam, periode , en geometire , straks kan op alles beetje fuzzy gezocht worden... 
- alle toponiemen van "naerden", Kan dat? Ter identificatie kan dit nl ook echt handig zijn...? Vlaggetje maken?
    -- ja dit gaat evt. als reverse geocoder , ondersteund worden. -> return invalide geoJSON.
- plaats weerd, liesIn Limburg
    -- hard ja, als het allemaal geresolved moet 
- uri voeren aan de API: bepalen wat je dan terug krijgt.
    -- ja! hogere prioriteit als het allemaal een beetje werkt..
- year=1600... meegeven aan de API.. wat wil dat eigenljk zeggen? Waar gaat de API dan op zoeken? Met marges? Exacte jaartal? Wat er het dichtst bij in de buurt ligt? Anders...?
Heeft een jaartal wel zin bij stanaardiseren?
- zoekacties beperken tot matches gevonden alleen in een bepaalde (of meerdere) source. UC: verdwenen dorp. Heeft als doel minder (multiple matches) terug te krijgen maar hoe kan dit?
    -- ja, dat kan. Ligt in de lijn der dingen...


