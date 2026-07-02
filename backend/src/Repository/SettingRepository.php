<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function get(string $name): ?string
    {
        return $this->findOneBy(['name' => $name])?->getValue();
    }

    /** Crée ou met à jour le réglage puis persiste. */
    public function set(string $name, string $value): void
    {
        $setting = $this->findOneBy(['name' => $name]) ?? (new Setting())->setName($name);
        $setting->setValue($value);

        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
    }
}
