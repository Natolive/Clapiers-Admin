<?php

namespace App\Entity\Enum;

/**
 * Nature d'un nœud de la médiathèque d'un membre : soit un dossier (conteneur),
 * soit un document (qui peut porter un fichier).
 */
enum MemberDocumentType: string
{
    case FOLDER = 'folder';
    case DOCUMENT = 'document';
}
