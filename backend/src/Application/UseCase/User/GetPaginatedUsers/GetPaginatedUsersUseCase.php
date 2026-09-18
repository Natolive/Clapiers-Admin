<?php

namespace App\Application\UseCase\User\GetPaginatedUsers;

use App\Common\Command\CommandInterface;
use App\Common\Service\MemberPhotoUrls;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;

/**
 * @extends AbstractUseCase<GetPaginatedUsersCommand>
 */
class GetPaginatedUsersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MemberPhotoUrls $photoUrls,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        $result = $this->userRepository->findPaginated(
            $command->page,
            $command->limit,
            $command->sortField,
            $command->sortOrder,
            $command->search,
        );

        // Le licencié rattaché porte son avatar comme dans les autres listes.
        $members = array_values(array_filter(array_map(
            static fn (AppUser $user) => $user->getMember(),
            $result['data'],
        )));
        $photos = $this->photoUrls->forMembers($members, $this->seasonProvider->current());

        return [
            'data' => array_map(
                function (AppUser $user) use ($photos): array {
                    $row = $user->toArray();
                    if ($row['member'] !== null) {
                        $row['member']['profilePictureUrl'] = $photos[$row['member']['id']] ?? null;
                    }

                    return $row;
                },
                $result['data'],
            ),
            'total' => $result['total'],
        ];
    }
}
