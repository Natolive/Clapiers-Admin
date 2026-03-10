<?php

namespace App\DataFixtures;

use App\Entity\AppUser;
use App\Entity\Game;
use App\Entity\Member;
use App\Entity\Team;
use App\Entity\ValueObject\Address;
use App\Entity\Enum\AppUserRole;
use App\Entity\Enum\GameVenue;
use App\Entity\Enum\MemberGender;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $teamsConfig = [
            ["name" => "U9 Garcons",   "minYear" => 2016, "maxYear" => 2018, "girls" => false],
            ["name" => "U11 Garcons",  "minYear" => 2014, "maxYear" => 2016, "girls" => false],
            ["name" => "U13 Garcons",  "minYear" => 2012, "maxYear" => 2014, "girls" => false],
            ["name" => "U13 Filles",   "minYear" => 2012, "maxYear" => 2014, "girls" => true],
            ["name" => "U15 Garcons",  "minYear" => 2010, "maxYear" => 2012, "girls" => false],
            ["name" => "U15 Filles",   "minYear" => 2010, "maxYear" => 2012, "girls" => true],
            ["name" => "U17 Garcons",  "minYear" => 2008, "maxYear" => 2010, "girls" => false],
            ["name" => "U19 Garcons",  "minYear" => 2006, "maxYear" => 2008, "girls" => false],
            ["name" => "Seniors A",    "minYear" => 1985, "maxYear" => 2004, "girls" => false],
            ["name" => "Seniors B",    "minYear" => 1988, "maxYear" => 2005, "girls" => false],
        ];

        $maleFirstNames   = ["Lucas", "Theo", "Hugo", "Nathan", "Tom", "Liam", "Ethan", "Adam", "Maxime", "Antoine", "Baptiste", "Clement", "Romain", "Julien", "Pierre", "Mathieu", "Alexis", "Thomas", "Nicolas", "Quentin", "Florian", "Adrien", "Kevin", "Dylan", "Yann", "Louis", "Arthur", "Raphael", "Enzo", "Axel"];
        $femaleFirstNames = ["Lea", "Chloe", "Emma", "Ines", "Manon", "Lucie", "Sarah", "Jade", "Camille", "Pauline", "Julie", "Marine", "Sophie", "Laura", "Elodie", "Ambre", "Clara", "Mila", "Zoe", "Alice", "Elisa", "Maelys", "Nina", "Lou", "Oceane", "Anais", "Celine", "Mathilde", "Noemie", "Lisa"];
        $lastNames        = ["Martin", "Bernard", "Thomas", "Petit", "Robert", "Richard", "Durand", "Leroy", "Moreau", "Simon", "Laurent", "Lefebvre", "Michel", "Garcia", "David", "Bertrand", "Roux", "Vincent", "Fournier", "Morel", "Girard", "Andre", "Mercier", "Dupont", "Blanc", "Bonnet", "Fontaine", "Chevalier", "Robin", "Noel"];
        $nationalities    = ["Francaise", "Espagnole", "Portugaise", "Marocaine", "Algerienne", "Italienne", "Tunisienne", "Francaise", "Francaise", "Francaise"];
        $streets          = ["12 rue des Lilas", "3 avenue du General de Gaulle", "7 impasse des Roses", "24 boulevard de la Republique", "15 chemin des Collines", "8 allee des Pins", "30 rue Victor Hugo", "5 place de la Mairie", "18 route de Montpellier", "42 rue Jean Jaures"];
        $opponents        = ["AS Montpellier", "FC Nimes", "Lunel FC", "AS Sete", "Mauguio FC", "Palavas Sports", "AS Baillargues", "Juvignac FC", "Grabels United", "AS Castelnau", "FC Lattes", "Vendargues SC", "AS Saint-Clement", "Jacou FC", "AS Perols", "FC Fabreges", "AS Cournonterral", "FC Murviel"];

        $mIdx = 0;
        $fIdx = 0;
        $lIdx = 0;

        $superAdmin = new AppUser();
        $superAdmin->setEmail("superadmin@clapiers.fr");
        $superAdmin->setRoles([AppUserRole::ROLE_SUPER_ADMIN]);
        $superAdmin->setPassword($this->hasher->hashPassword($superAdmin, "password"));
        $manager->persist($superAdmin);

        foreach ($teamsConfig as $cfg) {
            $team = new Team();
            $team->setName($cfg["name"]);
            $manager->persist($team);

            $memberCount = random_int(6, 8);
            for ($i = 0; $i < $memberCount; $i++) {
                $gender    = $cfg["girls"] ? MemberGender::FEMALE : MemberGender::MALE;
                $firstName = $cfg["girls"]
                    ? $femaleFirstNames[$fIdx % count($femaleFirstNames)]
                    : $maleFirstNames[$mIdx % count($maleFirstNames)];
                $lastName  = $lastNames[$lIdx % count($lastNames)];
                $year      = random_int($cfg["minYear"], $cfg["maxYear"]);
                $month     = random_int(1, 12);
                $day       = random_int(1, 28);

                $member = new Member();
                $member->setFirstName($firstName);
                $member->setLastName($lastName);
                $member->setEmail(strtolower($firstName . "." . $lastName) . ($mIdx + $fIdx + 1) . "@email.fr");
                $member->setPhoneNumber(sprintf("+336%08d", random_int(10000000, 99999999)));
                $member->setTeam($team);
                $member->setGender($gender);
                $member->setLicensePaid((bool) random_int(0, 1));
                $member->setLicenseNumber(random_int(0, 2) > 0 ? (string) random_int(100000000, 999999999) : null);
                $member->setNationality($nationalities[array_rand($nationalities)]);
                $member->setAddress(new Address($streets[array_rand($streets)], "34830", "Clapiers"));
                $member->setBirthDate(new \DateTimeImmutable("$year-$month-$day"));
                $manager->persist($member);

                $cfg["girls"] ? $fIdx++ : $mIdx++;
                $lIdx++;
            }

            $slug  = preg_replace("/[^a-z0-9]/", "", strtolower($cfg["name"]));
            $admin = new AppUser();
            $admin->setEmail("admin.$slug@clapiers.fr");
            $admin->setRoles([AppUserRole::ROLE_ADMIN]);
            $admin->setPassword($this->hasher->hashPassword($admin, "password"));
            $manager->persist($admin);

            $seasonStart = new \DateTimeImmutable("2024-09-01");
            $gameCount   = random_int(8, 12);
            for ($g = 0; $g < $gameCount; $g++) {
                $daysOffset = max(0, (int) ($g * (270 / $gameCount)) + random_int(-4, 4));
                $gameDate   = $seasonStart->modify("+{$daysOffset} days");
                $venue      = random_int(0, 1) === 0 ? GameVenue::HOME : GameVenue::AWAY;
                $hour       = random_int(9, 17);
                $min        = [0, 15, 30, 45][random_int(0, 3)];

                $game = new Game();
                $game->setTeam($team);
                $game->setOpponent($opponents[array_rand($opponents)]);
                $game->setDate($gameDate);
                $game->setVenue($venue);
                $game->setMeetingTime(sprintf("%02dh%02d", $hour, $min));
                $game->setLocation($venue === GameVenue::HOME ? "Stade Municipal de Clapiers" : null);
                $manager->persist($game);
            }
        }

        $manager->flush();
    }
}
