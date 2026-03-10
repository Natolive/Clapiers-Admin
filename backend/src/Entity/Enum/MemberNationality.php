<?php

namespace App\Entity\Enum;

enum MemberNationality: string
{
    case AFGHANE         = 'Afghane';
    case ALGERIENNE      = 'Algérienne';
    case ALLEMANDE       = 'Allemande';
    case AMERICAINE      = 'Américaine';
    case ANGOLAISE       = 'Angolaise';
    case ANGLAISE        = 'Anglaise';
    case BELGE           = 'Belge';
    case BENINOISE       = 'Béninoise';
    case BRESILIENNE     = 'Brésilienne';
    case BURKINABE       = 'Burkinabè';
    case CAMEROUNAISE    = 'Camerounaise';
    case CANADIENNE      = 'Canadienne';
    case CENTRAFRICAINE  = 'Centrafricaine';
    case CHINOISE        = 'Chinoise';
    case COLOMBIENNE     = 'Colombienne';
    case CONGOLAISE      = 'Congolaise';
    case COTE_DIVOIRE    = "Ivoirienne";
    case CROATE          = 'Croate';
    case CUBAINE         = 'Cubaine';
    case DANOISE         = 'Danoise';
    case EGYPTIENNE      = 'Égyptienne';
    case ESPAGNOLE       = 'Espagnole';
    case ETHIOPIENNE     = 'Éthiopienne';
    case FINNOISE        = 'Finnoise';
    case FRANCAISE       = 'Française';
    case GABONAISE       = 'Gabonaise';
    case GHANEENNE       = 'Ghanéenne';
    case GRECQUE         = 'Grecque';
    case GUINEENNE       = 'Guinéenne';
    case INDIENNE        = 'Indienne';
    case IRLANDAISE      = 'Irlandaise';
    case ISRAELIENNE     = 'Israélienne';
    case ITALIENNE       = 'Italienne';
    case JAPONAISE       = 'Japonaise';
    case KENYANE         = 'Kenyane';
    case LIBANAISE       = 'Libanaise';
    case LIBYENNE        = 'Libyenne';
    case LUXEMBOURGEOISE = 'Luxembourgeoise';
    case MALIENNE        = 'Malienne';
    case MAROCAINE       = 'Marocaine';
    case MAURITANIENNE   = 'Mauritanienne';
    case MEXICAINE       = 'Mexicaine';
    case MOZAMBICAINE    = 'Mozambicaine';
    case NEERLANDAISE    = 'Néerlandaise';
    case NIGERIANE       = 'Nigériane';
    case NIGERIENNE      = 'Nigérienne';
    case NORVEGIENNE     = 'Norvégienne';
    case PAKISTANAISE    = 'Pakistanaise';
    case POLONAISE       = 'Polonaise';
    case PORTUGAISE      = 'Portugaise';
    case ROUMAINE        = 'Roumaine';
    case RUSSE           = 'Russe';
    case RWANDAISE       = 'Rwandaise';
    case SENEGALAISE     = 'Sénégalaise';
    case SERBE           = 'Serbe';
    case SOMALIENNE      = 'Somalienne';
    case SUDAFRICAINE    = 'Sud-Africaine';
    case SUEDOISE        = 'Suédoise';
    case SUISSE          = 'Suisse';
    case SYRIENNE        = 'Syrienne';
    case TCHADIENNE      = 'Tchadienne';
    case TOGOLAISE       = 'Togolaise';
    case TUNISIENNE      = 'Tunisienne';
    case TURQUE          = 'Turque';
    case UKRAINIENNE     = 'Ukrainienne';
    case VIETNAMIENNE    = 'Vietnamienne';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
