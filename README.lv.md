# SEO-TidY

**Bezmaksas atvērtā koda WordPress SEO un MI meklēšanas gatavības spraudnis, ko izstrādā QvarcY.**

Sakārtots SEO. Labāka atrodamība. Pilnīgi bez maksas.

## Versijas statuss

Versija: **0.1.0-beta.1**

Šī ir testēšanai paredzēta beta versija. Pirms izmantošanas
publiskā vietnē nepieciešamas rezerves kopijas un saderības pārbaudes.

## Funkcijas

- SEO virsraksti un meta apraksti rakstiem un lapām
- Metadatu ievade bloku un klasiskajā redaktorā
- SEO metadatu ātrā rediģēšana administrācijā
- Automātiskie BlogPosting un WebPage JSON-LD strukturētie dati
- SEO metadatu un satura audits
- Satura struktūras ieteikumi gatavībai MI atbildēm
- Metadatu imports no Yoast SEO un Rank Math
- Centralizēti izvades un audita iestatījumi
- Administrācijas saskarne latviešu un angļu valodā

Satura ieteikumi ir orientējoši. Spraudnis negarantē
pozīcijas meklētājos, indeksēšanu vai iekļūšanu MI atbildēs.

## Prasības

- WordPress 6.8 vai jaunāks
- PHP 8.1 vai jaunāks

## Instalēšana

1. Lejupielādē ZIP failu no GitHub Releases.
2. WordPress atver Spraudņi > Pievienot jaunu > Augšupielādēt spraudni.
3. Izvēlies ZIP failu un instalē to.
4. Aktivizē SEO-TidY.
5. WordPress administrācijas izvēlnē atver SEO-TidY.

ZIP jau satur nepieciešamos JavaScript failus.
Parastai instalēšanai Node.js un npm nav vajadzīgs.

## Darba sākšana

1. Informācijas panelī apskati publicētā satura statistiku.
2. Sadaļā Metadati pārskati un rediģē SEO laukus.
3. Smart Schema vai Iestatījumos pārvaldi strukturētos datus.
4. SEO auditā un MI atbilžu gatavībā pārskati ieteikumus.
5. Pirms migrācijas izveido datubāzes rezerves kopiju.

Migrācija pārņem metadatus tikai tukšajos SEO-TidY laukos.
Avota spraudņa metadati netiek apzināti dzēsti.
Pirms importa pārskati priekšskatījumu un apstiprini darbību.

Vairāku SEO spraudņu vienlaicīga lietošana var radīt
dublētus metadatus vai strukturētos datus.
Pirms publicēšanas pārbaudi lapas HTML izvadi.

## Izstrāde

Atkarību instalēšana: `npm ci`
Būvēšana: `npm run build`

Pirmkods: https://github.com/QvarcY/wordpress-seo-tidy

## Licence

GPL-2.0-or-later. Skatīt [LICENSE](LICENSE).

## Atbalsts

- GitHub: https://github.com/QvarcY
- Ziņot par problēmu: https://github.com/QvarcY/wordpress-seo-tidy/issues
- Buy Me a Coffee: https://buymeacoffee.com/craftin

Atbalsts ir brīvprātīgs. Visas SEO-TidY funkcijas paliek bezmaksas.