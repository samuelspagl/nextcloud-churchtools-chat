<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Command;

use OCA\ChurchToolsChat\Service\ProbeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * D5 diagnostic: print the ChurchTools chat metadata and the joined Matrix rooms
 * side by side so the CT chat -> room mapping can be confirmed.
 */
final class ProbeCommand extends Command {
	public function __construct(
		private readonly ProbeService $probe,
	) {
		parent::__construct('churchtools_chat:probe');
	}

	protected function configure(): void {
		$this->setDescription('Admin-only: dump ChurchTools /api/chat and joined Matrix rooms to verify their mapping.')
			->addArgument('userId', InputArgument::REQUIRED, 'Nextcloud user id');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = (string)$input->getArgument('userId');
		try {
			$data = $this->probe->collect($userId);
		} catch (\Throwable $exception) {
			$output->writeln('<error>' . $exception->getMessage() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln('tenantUrl: ' . $data['tenantUrl']);
		$output->writeln('');
		$output->writeln('== ChurchTools /api/chat (raw) ==');
		$output->writeln(json_encode($data['churchToolsChats'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		$output->writeln('');
		$output->writeln('== Matrix rooms (joined/invited, relevant state) ==');
		foreach ($data['matrixRooms'] as $room) {
			$output->writeln('--- ' . $room['roomId'] . ' (' . $room['membership'] . ')');
			$output->writeln('state types: ' . implode(', ', array_keys($room['stateTypes'])));
			$output->writeln(json_encode($room['state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		}
		$output->writeln('');
		$output->writeln('== Suggested mapping (hypothesis, resolved via directory) ==');
		foreach ($data['suggestedMappings'] as $suggested) {
			$chat = $suggested['chat'];
			$resolved = $suggested['resolvedRoomId'] ?? null;
			$output->writeln(
				$chat['prefix'] . ' ' . $chat['guid'] . ' ' . ($chat['roomname'] ?? '')
				. ' -> ' . $suggested['candidateAlias']
				. ($resolved !== null ? '  [RESOLVED: ' . $resolved . ']' : '  [alias not found]'),
			);
		}
		return Command::SUCCESS;
	}
}
