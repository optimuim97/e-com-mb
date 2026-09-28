/**
 * Départements de Côte d'Ivoire et leurs sous-préfectures.
 *
 * La liste précédente reposait sur le découpage d'avant 2011 : 57 entrées qui
 * confondaient anciens départements et sous-préfectures. Korhogo — 440 000
 * habitants, chef-lieu de la région du Poro et capitale du district des
 * Savanes — y figurait comme une commune de Boundiali, et n'était donc pas
 * proposable comme destination. Sinématiali, Niofoin ou Sirasso subissaient le
 * même sort ; à l'inverse, Ferkessédougou avalait Kong et Ouangolodougou, qui
 * sont des départements à part entière.
 *
 * Elle suit désormais le découpage en vigueur : 111 départements, chacun avec
 * ses sous-préfectures. C'est le bon niveau pour une destination de livraison —
 * la cliente nomme sa sous-préfecture, la boutique tarife au département.
 *
 * Le district autonome d'Abidjan ouvre la liste : il concentre l'essentiel des
 * commandes, et ses communes sont de toute façon remplacées à l'exécution par
 * les zones de l'administration (voir abidjan-communes.js).
 *
 * Sources : Wikipédia — « Départements de la Côte d'Ivoire » et
 * « Sub-prefectures of Ivory Coast », consultées en septembre 2026.
 */
export const citiesCI = [
  {
    id: 1,
    name: "Abidjan",
    region: "District autonome d'Abidjan",
    communes: [
      "Abobo", "Adjamé", "Anyama", "Attécoubé", "Bingerville", "Brofodoumé", "Cocody",
      "Koumassi", "Marcory", "Plateau", "Port-Bouët", "Songon", "Treichville",
      "Yopougon"
    ]
  },
  {
    id: 2,
    name: "Abengourou",
    region: "Indénié-Djuablin",
    communes: [
      "Abengourou", "Amélékia", "Aniassué", "Ebilassokro", "Niablé", "Yakassé-Féyassé",
      "Zaranou"
    ]
  },
  {
    id: 3,
    name: "Aboisso",
    region: "Sud-Comoé",
    communes: ["Aboisso", "Adaou", "Adjouan", "Ayamé", "Bianouan", "Kouakro", "Maféré", "Yaou"]
  },
  {
    id: 4,
    name: "Adiaké",
    region: "Sud-Comoé",
    communes: ["Adiaké", "Assinie-Mafia", "Etuéboué"]
  },
  {
    id: 5,
    name: "Adzopé",
    region: "La Mé",
    communes: ["Adzopé", "Agou", "Annépé", "Assikoi", "Bécédi-Brignan", "Yakassé-Mé"]
  },
  {
    id: 6,
    name: "Agboville",
    region: "Agnéby-Tiassa",
    communes: [
      "Aboudé", "Agboville", "Ananguié", "Attobrou", "Azaguié", "Céchi", "Grand-Morié",
      "Guessiguié", "Loviguié", "Oress-Krobou", "Rubino"
    ]
  },
  {
    id: 7,
    name: "Agnibilékrou",
    region: "Indénié-Djuablin",
    communes: ["Agnibilékrou", "Akoboissué", "Damé", "Duffrébo", "Tanguélan"]
  },
  {
    id: 8,
    name: "Akoupé",
    region: "La Mé",
    communes: ["Afféry", "Akoupé", "Bécouéfin"]
  },
  {
    id: 9,
    name: "Alépé",
    region: "La Mé",
    communes: ["Aboisso-Comoé", "Alépé", "Allosso", "Danguira", "Oghlwapo"]
  },
  {
    id: 10,
    name: "Arrah",
    region: "Moronou",
    communes: ["Arrah", "Kotobi", "Krégbé"]
  },
  {
    id: 11,
    name: "Attiégouakro",
    region: "District autonome de Yamoussoukro",
    communes: ["Attiégouakro", "Lolobo"]
  },
  {
    id: 12,
    name: "Bangolo",
    region: "Guémon",
    communes: [
      "Bangolo", "Béoué-Zibiao", "Bléniméouin", "Diéouzon", "Gohouo-Zagna",
      "Guinglo-Tahouaké", "Kahin-Zarabaon", "Zéo", "Zou"
    ]
  },
  {
    id: 13,
    name: "Béoumi",
    region: "Gbêkê",
    communes: [
      "Ando-Kékrénou", "Béoumi", "Bodokro", "Kondrobo", "Lolobo", "Marabadiassa",
      "N'Guessankro"
    ]
  },
  {
    id: 14,
    name: "Bettié",
    region: "Indénié-Djuablin",
    communes: ["Bettié", "Diamarakro"]
  },
  {
    id: 15,
    name: "Biankouma",
    region: "Tonkpi",
    communes: ["Biankouma", "Blapleu", "Gbangbégouiné", "Gbonné", "Gouiné", "Kpata", "Santa"]
  },
  {
    id: 16,
    name: "Bloléquin",
    region: "Cavally",
    communes: ["Bloléquin", "Diboké", "Doké", "Tinhou", "Zéaglo"]
  },
  {
    id: 17,
    name: "Bocanda",
    region: "N'Zi",
    communes: ["Bengassou", "Bocanda", "Kouadioblékro", "N'Zécrézessou"]
  },
  {
    id: 18,
    name: "Bondoukou",
    region: "Gontougo",
    communes: [
      "Appimandoum", "Bondo", "Bondoukou", "Gouméré", "Laoudi-Ba", "Pinda-Boroko",
      "Sapli-Sépingo", "Sorobango", "Tabagne", "Tagadi", "Taoudi", "Yézimala"
    ]
  },
  {
    id: 19,
    name: "Bongouanou",
    region: "Moronou",
    communes: ["Andé", "Assié-Koumassi", "Bongouanou", "N'Guessankro"]
  },
  {
    id: 20,
    name: "Bonon",
    region: "Marahoué",
    communes: ["Bonon"]
  },
  {
    id: 21,
    name: "Botro",
    region: "Gbêkê",
    communes: ["Botro", "Diabo", "Krofoinsou", "Languibonou"]
  },
  {
    id: 22,
    name: "Bouaflé",
    region: "Marahoué",
    communes: ["Bégbessou", "Bouaflé", "N'Douffoukankro", "Pakouabo", "Tibéita", "Zaguiéta"]
  },
  {
    id: 23,
    name: "Bouaké",
    region: "Gbêkê",
    communes: ["Bouaké", "Bounda", "Brobo", "Mamini", "N'Djébonouan"]
  },
  {
    id: 24,
    name: "Bouna",
    region: "Bounkani",
    communes: ["Bouko", "Bouna", "Ondéfidouo", "Youndouo"]
  },
  {
    id: 25,
    name: "Boundiali",
    region: "Bagoué",
    communes: ["Baya", "Boundiali", "Ganaoni", "Kasséré", "Siempurgo"]
  },
  {
    id: 26,
    name: "Buyo",
    region: "Nawa",
    communes: ["Buyo", "Dapéoua"]
  },
  {
    id: 27,
    name: "Dabakala",
    region: "Hambol",
    communes: [
      "Bassawa", "Boniérédougou", "Dabakala", "Foumbolo", "Niéméné", "Satama-Sokoro",
      "Satama-Sokoura", "Sokala-Sobara", "Tendéné-Bambarasso", "Yaossédougou"
    ]
  },
  {
    id: 28,
    name: "Dabou",
    region: "Grands-Ponts",
    communes: ["Dabou", "Lopou", "Toupah"]
  },
  {
    id: 29,
    name: "Daloa",
    region: "Haut-Sassandra",
    communes: ["Bédiala", "Daloa", "Gadouan", "Gboguhé", "Gonaté", "Zaïbo"]
  },
  {
    id: 30,
    name: "Danané",
    region: "Tonkpi",
    communes: ["Daleu", "Danané", "Gbon-Houyé", "Kouan-Houlé", "Mahapleu", "Séileu", "Zonneu"]
  },
  {
    id: 31,
    name: "Daoukro",
    region: "Iffou",
    communes: ["Akpassanou", "Ananda", "Daoukro", "Ettrokro", "N'Gattakro", "Samanza"]
  },
  {
    id: 32,
    name: "Dianra",
    region: "Béré",
    communes: ["Dianra", "Dianra-Village"]
  },
  {
    id: 33,
    name: "Didiévi",
    region: "Bélier",
    communes: ["Boli", "Didiévi", "Molonou-Blé", "Raviart", "Tié-N'Diékro"]
  },
  {
    id: 34,
    name: "Dikodougou",
    region: "Poro",
    communes: ["Boron", "Dikodougou", "Guiembé"]
  },
  {
    id: 35,
    name: "Dimbokro",
    region: "N'Zi",
    communes: ["Abigui", "Diangokro", "Dimbokro", "Nofou"]
  },
  {
    id: 36,
    name: "Divo",
    region: "Lôh-Djiboua",
    communes: ["Chiépo", "Didoko", "Divo", "Hiré", "Nébo", "Ogoudou", "Zégo"]
  },
  {
    id: 37,
    name: "Djékanou",
    region: "Bélier",
    communes: ["Bonikro", "Djékanou"]
  },
  {
    id: 38,
    name: "Doropo",
    region: "Bounkani",
    communes: ["Danoa", "Doropo", "Kalamon", "Niamoué"]
  },
  {
    id: 39,
    name: "Duékoué",
    region: "Guémon",
    communes: ["Bagohouo", "Duékoué", "Gbapleu", "Guéhiébly", "Guézon"]
  },
  {
    id: 40,
    name: "Facobly",
    region: "Guémon",
    communes: ["Facobly", "Guézon", "Koua", "Sémien", "Tiény-Séably"]
  },
  {
    id: 41,
    name: "Ferkessédougou",
    region: "Tchologo",
    communes: ["Ferkessédougou", "Koumbala", "Togoniéré"]
  },
  {
    id: 42,
    name: "Fresco",
    region: "Gbôklé",
    communes: ["Dahiri", "Fresco", "Gbagbam"]
  },
  {
    id: 43,
    name: "Gagnoa",
    region: "Gôh",
    communes: [
      "Bayota", "Dahiépa-Kéhi", "Dignago", "Dougroupalégnaoa", "Doukouyo", "Gagnoa",
      "Galébré-Galébouo", "Gnagbodougnoa", "Guibéroua", "Ouragahio", "Sérihio",
      "Yopohué"
    ]
  },
  {
    id: 44,
    name: "Gbéléban",
    region: "Kabadougou",
    communes: ["Gbéléban", "Samango", "Seydougou"]
  },
  {
    id: 45,
    name: "Gohitafla",
    region: "Marahoué",
    communes: ["Gohitafla", "Iriéfla", "Maminigui"]
  },
  {
    id: 46,
    name: "Grand-Bassam",
    region: "Sud-Comoé",
    communes: ["Bongo", "Bonoua", "Grand-Bassam"]
  },
  {
    id: 47,
    name: "Grand-Lahou",
    region: "Grands-Ponts",
    communes: ["Ahouanou", "Bacanda", "Ebonou", "Grand-Lahou", "Toukouzou"]
  },
  {
    id: 48,
    name: "Guéyo",
    region: "Nawa",
    communes: ["Dabouyo", "Guéyo"]
  },
  {
    id: 49,
    name: "Guiglo",
    region: "Cavally",
    communes: ["Bédy-Goazon", "Guiglo", "Kaadé", "Nizahon"]
  },
  {
    id: 50,
    name: "Guitry",
    region: "Lôh-Djiboua",
    communes: ["Dairo-Didizo", "Guitry", "Lauzoua", "Yocoboué"]
  },
  {
    id: 51,
    name: "Issia",
    region: "Haut-Sassandra",
    communes: ["Boguédia", "Iboguhé", "Issia", "Nahio", "Namané", "Saïoua", "Tapéguia"]
  },
  {
    id: 52,
    name: "Jacqueville",
    region: "Grands-Ponts",
    communes: ["Attoutou", "Jacqueville"]
  },
  {
    id: 53,
    name: "Kani",
    region: "Worodougou",
    communes: ["Djibrosso", "Fadiadougou", "Kani", "Morondo"]
  },
  {
    id: 54,
    name: "Kaniasso",
    region: "Folon",
    communes: ["Goulia", "Kaniasso", "Mahandiana-Sokourani"]
  },
  {
    id: 55,
    name: "Katiola",
    region: "Hambol",
    communes: ["Fronan", "Katiola", "Timbé"]
  },
  {
    id: 56,
    name: "Kong",
    region: "Tchologo",
    communes: ["Bilimono", "Kong", "Nafana", "Sikolo"]
  },
  {
    id: 57,
    name: "Korhogo",
    region: "Poro",
    communes: [
      "Dassoungboho", "Kanoroba", "Karakoro", "Kiémou", "Kombolokoura", "Komborodougou",
      "Koni", "Korhogo", "Lataha", "Nafoun", "Napiéolédougou", "N'Ganon", "Niofoin",
      "Sirasso", "Sohouo", "Tioroniaradougou"
    ]
  },
  {
    id: 58,
    name: "Koro",
    region: "Bafing",
    communes: ["Booko", "Borotou", "Koro", "Mahandougou", "Niokosso"]
  },
  {
    id: 59,
    name: "Kouassi-Kouassikro",
    region: "N'Zi",
    communes: ["Kouassi-Kouassikro", "Mékro"]
  },
  {
    id: 60,
    name: "Kouibly",
    region: "Guémon",
    communes: ["Kouibly", "Nidrou", "Ouyably-Gnondrou", "Totrodrou"]
  },
  {
    id: 61,
    name: "Koun-Fao",
    region: "Gontougo",
    communes: ["Boahia", "Kokomian", "Kouassi-Datékro", "Koun-Fao", "Tankessé", "Tienkoikro"]
  },
  {
    id: 62,
    name: "Kounahiri",
    region: "Béré",
    communes: ["Kongasso", "Kounahiri"]
  },
  {
    id: 63,
    name: "Kouto",
    region: "Bagoué",
    communes: ["Blességué", "Gbon", "Kolia", "Kouto", "Sianhala"]
  },
  {
    id: 64,
    name: "Lakota",
    region: "Lôh-Djiboua",
    communes: ["Djidji", "Gagoré", "Goudouko", "Lakota", "Niambézaaria", "Zikisso"]
  },
  {
    id: 65,
    name: "M'Bahiakro",
    region: "Iffou",
    communes: ["Bonguéra", "Kondossou", "M'Bahiakro"]
  },
  {
    id: 66,
    name: "M'Batto",
    region: "Moronou",
    communes: ["Anoumaba", "Assahara", "M'Batto", "Tiémélékro"]
  },
  {
    id: 67,
    name: "M'Bengué",
    region: "Poro",
    communes: ["Bougou", "Katiali", "Katogo", "M'Bengué"]
  },
  {
    id: 68,
    name: "Madinani",
    region: "Kabadougou",
    communes: ["Fengolo", "Madinani", "N'Goloblasso"]
  },
  {
    id: 69,
    name: "Man",
    region: "Tonkpi",
    communes: [
      "Bogouiné", "Fagnampleu", "Gbangbégouiné-Yati", "Logoualé", "Man", "Podiagouiné",
      "Sandougou-Soba", "Sangouiné", "Yapleu", "Zagoué", "Ziogouiné"
    ]
  },
  {
    id: 70,
    name: "Mankono",
    region: "Béré",
    communes: ["Bouandougou", "Mankono", "Marandallah", "Sarhala", "Tiéningboué"]
  },
  {
    id: 71,
    name: "Méagui",
    region: "Nawa",
    communes: ["Gnanmangui", "Méagui", "Oupoyo"]
  },
  {
    id: 72,
    name: "Minignan",
    region: "Folon",
    communes: ["Kimbirila-Nord", "Minignan", "Sokoro", "Tienko"]
  },
  {
    id: 73,
    name: "Nassian",
    region: "Bounkani",
    communes: ["Bogofa", "Kakpin", "Koutouba", "Nassian", "Sominassé"]
  },
  {
    id: 74,
    name: "Niakaramandougou",
    region: "Hambol",
    communes: ["Arikokaha", "Badikaha", "Niakaramandougou", "Niédiékaha", "Tafiré", "Tortiya"]
  },
  {
    id: 75,
    name: "Odienné",
    region: "Kabadougou",
    communes: ["Bako", "Bougousso", "Dioulatièdougou", "Odienné", "Tiémé"]
  },
  {
    id: 76,
    name: "Ouangolodougou",
    region: "Tchologo",
    communes: ["Diawala", "Kaouara", "Niellé", "Ouangolodougou", "Toumoukoro"]
  },
  {
    id: 77,
    name: "Ouaninou",
    region: "Bafing",
    communes: ["Gbélo", "Gouékan", "Koonan", "Ouaninou", "Saboudougou", "Santa"]
  },
  {
    id: 78,
    name: "Ouellé",
    region: "Iffou",
    communes: ["Ouellé"]
  },
  {
    id: 79,
    name: "Oumé",
    region: "Gôh",
    communes: ["Diégonéfla", "Guépahouo", "Oumé", "Tonla"]
  },
  {
    id: 80,
    name: "Prikro",
    region: "Iffou",
    communes: ["Anianou", "Famienkro", "Koffi-Amonkro", "Nafana", "Prikro"]
  },
  {
    id: 81,
    name: "Sakassou",
    region: "Gbêkê",
    communes: ["Ayaou-Sran", "Dibri-Assirikro", "Sakassou", "Toumodi-Sakassou"]
  },
  {
    id: 82,
    name: "Samatiguila",
    region: "Kabadougou",
    communes: ["Kimbirila-Sud", "Samatiguila"]
  },
  {
    id: 83,
    name: "San-Pédro",
    region: "San-Pédro",
    communes: ["Doba", "Dogbo", "Gabiadji", "Grand-Béréby", "San-Pédro"]
  },
  {
    id: 84,
    name: "Sandégué",
    region: "Gontougo",
    communes: ["Bandakagni-Tomora", "Dimandougou", "Sandégué", "Yorobodi"]
  },
  {
    id: 85,
    name: "Sassandra",
    region: "Gbôklé",
    communes: ["Dakpadou", "Grihiri", "Lobakuya", "Médon", "Sago", "Sassandra"]
  },
  {
    id: 86,
    name: "Séguéla",
    region: "Worodougou",
    communes: ["Bobi-Diarabana", "Dualla", "Kamalo", "Massala", "Séguéla", "Sifié", "Worofla"]
  },
  {
    id: 87,
    name: "Séguélon",
    region: "Kabadougou",
    communes: ["Gbongaha", "Séguélon"]
  },
  {
    id: 88,
    name: "Sikensi",
    region: "Agnéby-Tiassa",
    communes: ["Gomon", "Sikensi"]
  },
  {
    id: 89,
    name: "Sinématiali",
    region: "Poro",
    communes: ["Bouakaha", "Kagbolodougou", "Sédiego", "Sinématiali"]
  },
  {
    id: 90,
    name: "Sinfra",
    region: "Marahoué",
    communes: ["Bazré", "Kononfla", "Kouétinfla", "Sinfra"]
  },
  {
    id: 91,
    name: "Sipilou",
    region: "Tonkpi",
    communes: ["Sipilou", "Yorodougou"]
  },
  {
    id: 92,
    name: "Soubré",
    region: "Nawa",
    communes: ["Grand-Zattry", "Liliyo", "Okrouyo", "Soubré"]
  },
  {
    id: 93,
    name: "Taabo",
    region: "Agnéby-Tiassa",
    communes: ["Pacobo", "Taabo"]
  },
  {
    id: 94,
    name: "Tabou",
    region: "San-Pédro",
    communes: ["Dapo-Iboké", "Djamandioké", "Djouroutou", "Grabo", "Olodio", "Tabou"]
  },
  {
    id: 95,
    name: "Taï",
    region: "Cavally",
    communes: ["Taï", "Zagné"]
  },
  {
    id: 96,
    name: "Tanda",
    region: "Gontougo",
    communes: ["Amanvi", "Diamba", "Tanda", "Tchédio"]
  },
  {
    id: 97,
    name: "Téhini",
    region: "Bounkani",
    communes: ["Gogo", "Téhini", "Tougbo"]
  },
  {
    id: 98,
    name: "Tengréla",
    region: "Bagoué",
    communes: ["Débété", "Kanakono", "Papara", "Tengréla"]
  },
  {
    id: 99,
    name: "Tiapoum",
    region: "Sud-Comoé",
    communes: ["Noé", "Nouamou", "Tiapoum"]
  },
  {
    id: 100,
    name: "Tiassalé",
    region: "Agnéby-Tiassa",
    communes: ["Gbolouville", "Morokro", "N'Douci", "Tiassalé"]
  },
  {
    id: 101,
    name: "Tiébissou",
    region: "Bélier",
    communes: ["Lomokankro", "Molonou", "Tiébissou", "Yakpabo-Sakassou"]
  },
  {
    id: 102,
    name: "Touba",
    region: "Bafing",
    communes: ["Dioman", "Foungbesso", "Guintéguéla", "Touba"]
  },
  {
    id: 103,
    name: "Toulépleu",
    region: "Cavally",
    communes: ["Bakoubli", "Méo", "Nézobly", "Péhé", "Tiobly", "Toulépleu"]
  },
  {
    id: 104,
    name: "Toumodi",
    region: "Bélier",
    communes: ["Angoda", "Kokumbo", "Kpouèbo", "Toumodi"]
  },
  {
    id: 105,
    name: "Transua",
    region: "Gontougo",
    communes: ["Assuéfry", "Kouassia-Niaguini", "Transua"]
  },
  {
    id: 106,
    name: "Vavoua",
    region: "Haut-Sassandra",
    communes: ["Bazra-Nattis", "Dananon", "Dania", "Kétro-Bassam", "Séitifla", "Vavoua"]
  },
  {
    id: 107,
    name: "Yakassé-Attobrou",
    region: "La Mé",
    communes: ["Abongoua", "Biéby", "Yakassé-Attobrou"]
  },
  {
    id: 108,
    name: "Yamoussoukro",
    region: "District autonome de Yamoussoukro",
    communes: ["Kossou", "Yamoussoukro"]
  },
  {
    id: 109,
    name: "Zouan-Hounien",
    region: "Tonkpi",
    communes: ["Banneu", "Bin-Houyé", "Goulaleu", "Téapleu", "Yelleu", "Zouan-Hounien"]
  },
  {
    id: 110,
    name: "Zoukougbeu",
    region: "Haut-Sassandra",
    communes: ["Domangbeu", "Grégbeu", "Guessabo", "Zoukougbeu"]
  },
  {
    id: 111,
    name: "Zuénoula",
    region: "Marahoué",
    communes: ["Kanzra", "Vouéboufla", "Zanzra", "Zuénoula"]
  }
]

/**
 * Détection « Abidjan », déléguée au module des zones de livraison.
 *
 * La liste vivait ici, dérivée des treize communes officielles ci-dessus.
 * Elle ignorait les quartiers pourtant desservis — Abatta, Faya, Riviera —
 * et les faisait traiter comme l'intérieur du pays. C'est désormais
 * l'administration qui décide, via les zones du groupe « Grand Abidjan ».
 *
 * Réexporté pour que les écrans existants n'aient rien à changer.
 */
export { isAbidjan } from './abidjan-communes.js'

export default citiesCI
