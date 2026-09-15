<?php

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * Recherche « humaine » sur un membre, partagée par la liste des licenciés et
 * celle des demandes de licence : chaque mot saisi doit se retrouver quelque
 * part dans prénom / nom / email / téléphone, quel que soit l'ordre (« jean dup »
 * comme « dup jean » trouvent Jean Dupont), sans se soucier de la casse ni des
 * accents.
 *
 * Le côté colonne est préparé par Postgres (`member.search_text`, générée et
 * indexée en trigram) ; on aligne ici le côté saisie : minuscules, accents
 * retirés, séparateurs de numéro de téléphone supprimés.
 */
final class MemberSearchFilter
{
    /**
     * @param string $alias alias DQL de l'entité Member dans la requête
     */
    public static function apply(QueryBuilder $qb, string $alias, ?string $search): void
    {
        foreach (self::terms($search) as $i => $term) {
            $qb->andWhere($alias.'.searchText LIKE :memberSearch'.$i)
                ->setParameter('memberSearch'.$i, '%'.$term.'%');
        }
    }

    /**
     * @return list<string>
     */
    private static function terms(?string $search): array
    {
        $raw = trim((string) $search);

        if ($raw === '') {
            return [];
        }

        // Un numéro saisi « 06 12 34 56 » ne doit pas être découpé en morceaux
        // qui matcheraient n'importe quel champ : on le recolle sans séparateurs,
        // comme la colonne, qui contient aussi le numéro sans séparateurs et
        // ramené au format national (un +33 stocké se trouve donc en tapant 06).
        if (preg_match('/^[\d\s.\-\/+()]+$/', $raw) === 1) {
            $digits = preg_replace('/\D+/', '', $raw);

            return $digits === '' ? [] : [$digits];
        }

        return array_map(self::normalize(...), preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Même normalisation que `f_unaccent(lower(...))` côté Postgres.
     */
    private static function normalize(string $term): string
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');

        return mb_strtolower($transliterator?->transliterate($term) ?: $term);
    }
}
