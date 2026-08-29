<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

interface RoomDetailsProvider {
	/** @return array<string,mixed> */
	public function getRoomDetails(string $userId, string $roomId): array;
}
