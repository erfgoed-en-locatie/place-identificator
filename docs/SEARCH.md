#How to Search in the API

##Quoted & exact=true

Searches the API on a literal (quoted) string, and returns only exact matches (not part of)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=true
     
Wy do we get those results back?

##Quoted, not exact

Searches the API on a literal string, and returns also matches that were partially found
     * (Bergen op zoomstraat)
     * Example: http://api.histograph.io/search?name="Bergen op Zoom"&exact=false
     
## match a word
    Searches the API on a (tokenized) word that is contained by the placename
     * Example: http://api.histograph.io/search?name=Bergen op Zoom&exact=true

## default (most forgiving)
    Searches the API on a (tokenized) word that exactly matches a placename (so no matches on partial names or search strings)
     * Example: http://api.histograph.io/search?name=Bergen op Zoom
     


Make an example/ showcase thingy...

Zoeken op soort concept

En laten zien welke andere opties er zijn.

Wild cards gebruiken



