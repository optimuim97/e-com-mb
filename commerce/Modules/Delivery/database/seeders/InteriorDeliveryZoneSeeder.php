<?php

namespace Modules\Delivery\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Delivery\Models\DeliveryZone;

/**
 * Tarife à 3 000 FCFA tous les départements de l'intérieur encore sans zone.
 *
 * Douze villes seulement étaient tarifées : partout ailleurs, la commande
 * partait en « frais à confirmer », bloquée à l'expédition tant qu'une agente
 * n'avait pas rappelé la cliente. Sur les 110 départements du pays, ça faisait
 * 98 destinations traitées à la main.
 *
 * N'écrit que ce qui manque : une zone déjà en base garde son tarif, y compris
 * s'il a été ajusté en administration. Le seeder est donc rejouable, et ne
 * défait jamais un arbitrage humain.
 *
 * Tarif unique assumé : c'est un point de départ, pas une grille définitive.
 * Tengréla est à 700 km d'Abidjan et Agboville à 80 — les écarts se corrigent
 * ensuite zone par zone, et ce seeder n'y reviendra pas.
 *
 * Les noms suivent le découpage en vigueur, aligné sur
 * commerce-front/src/data/cities-ci.js. Les deux listes doivent rester
 * cohérentes ; un écart ne crée pas de doublon pour autant, la vérification
 * ci-dessous s'en charge.
 */
class InteriorDeliveryZoneSeeder extends Seeder
{
    /** Tarif appliqué aux destinations encore inconnues du barème. */
    private const PRICE = 3000;

    private const GROUP = 'Intérieur CI';

    public function run(): void
    {
        /*
         * Appellations déjà couvertes : noms de zones ET alias.
         *
         * Les alias sont indispensables ici. La zone « Grand Lahou » s'écrit
         * sans trait d'union et « San Pedro » sans accent : comparés au seul
         * nom, les départements « Grand-Lahou » et « San-Pédro » passeraient
         * pour absents et seraient créés une seconde fois. Leurs alias
         * `grand-lahou` et `san-pedro`, eux, correspondent exactement.
         */
        $covered = DeliveryZone::where('country', 'CI')
            ->get()
            ->flatMap(fn (DeliveryZone $zone) => [$zone->name, ...($zone->cities ?? [])])
            ->filter(fn ($nom) => is_string($nom) && trim($nom) !== '')
            ->map(fn (string $nom) => DeliveryZone::normalize($nom))
            ->flip();

        $order = (int) DeliveryZone::where('country', 'CI')->max('sort_order') + 1;
        $added = 0;

        foreach (self::DEPARTMENTS as $name) {
            $key = DeliveryZone::normalize($name);

            if ($covered->has($key)) {
                continue;
            }

            DeliveryZone::create([
                'group'      => self::GROUP,
                'name'       => $name,
                'country'    => 'CI',
                // Aucun alias : le nom du département suffit, et le résolveur
                // compare déjà sans accents ni casse. Ajouter les
                // sous-préfectures ferait entrer en collision les homonymes
                // — « Guézon » existe sous Duékoué comme sous Facobly.
                'cities'     => [],
                'price'      => self::PRICE,
                'price_unit' => 'flat',
                'sort_order' => $order++,
                'active'     => true,
            ]);

            // La zone qu'on vient de créer couvre désormais son nom : deux
            // entrées homonymes dans la liste n'en produiraient qu'une.
            $covered->put($key, true);
            $added++;
        }

        $this->command?->info(
            $added > 0
                ? "{$added} destination(s) de l'intérieur tarifée(s) à " . self::PRICE . ' FCFA.'
                : 'Toutes les destinations de l\'intérieur sont déjà tarifées.'
        );
    }

    /**
     * Les 110 départements hors district d'Abidjan.
     *
     * Abidjan en est absent : ses communes relèvent du groupe « Grand Abidjan »
     * et de son barème propre.
     */
    private const DEPARTMENTS = [
        'Abengourou', 'Aboisso', 'Adiaké', 'Adzopé', 'Agboville', 'Agnibilékrou',
        'Akoupé', 'Alépé', 'Arrah', 'Attiégouakro', 'Bangolo', 'Béoumi', 'Bettié',
        'Biankouma', 'Bloléquin', 'Bocanda', 'Bondoukou', 'Bongouanou', 'Bonon',
        'Botro', 'Bouaflé', 'Bouaké', 'Bouna', 'Boundiali', 'Buyo', 'Dabakala', 'Dabou',
        'Daloa', 'Danané', 'Daoukro', 'Dianra', 'Didiévi', 'Dikodougou', 'Dimbokro',
        'Divo', 'Djékanou', 'Doropo', 'Duékoué', 'Facobly', 'Ferkessédougou', 'Fresco',
        'Gagnoa', 'Gbéléban', 'Gohitafla', 'Grand-Bassam', 'Grand-Lahou', 'Guéyo',
        'Guiglo', 'Guitry', 'Issia', 'Jacqueville', 'Kani', 'Kaniasso', 'Katiola',
        'Kong', 'Korhogo', 'Koro', 'Kouassi-Kouassikro', 'Kouibly', 'Koun-Fao',
        'Kounahiri', 'Kouto', 'Lakota', 'M\'Bahiakro', 'M\'Batto', 'M\'Bengué',
        'Madinani', 'Man', 'Mankono', 'Méagui', 'Minignan', 'Nassian',
        'Niakaramandougou', 'Odienné', 'Ouangolodougou', 'Ouaninou', 'Ouellé', 'Oumé',
        'Prikro', 'Sakassou', 'Samatiguila', 'San-Pédro', 'Sandégué', 'Sassandra',
        'Séguéla', 'Séguélon', 'Sikensi', 'Sinématiali', 'Sinfra', 'Sipilou', 'Soubré',
        'Taabo', 'Tabou', 'Taï', 'Tanda', 'Téhini', 'Tengréla', 'Tiapoum', 'Tiassalé',
        'Tiébissou', 'Touba', 'Toulépleu', 'Toumodi', 'Transua', 'Vavoua',
        'Yakassé-Attobrou', 'Yamoussoukro', 'Zouan-Hounien', 'Zoukougbeu', 'Zuénoula',
    ];
}
