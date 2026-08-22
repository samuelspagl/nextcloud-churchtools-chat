<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Service\MatrixRoomMapper;
use PHPUnit\Framework\TestCase;

final class MatrixRoomMapperTest extends TestCase {
	private MatrixRoomMapper $mapper;

	protected function setUp(): void {
		$this->mapper = new MatrixRoomMapper();
	}

	public function testRoomLocalMemberNameWinsOverGlobalProfile(): void {
		$members = $this->mapper->members([
			$this->member('@ct_anna:chat.church.tools', 'Anna im Team'),
		], [
			'@ct_anna:chat.church.tools' => ['displayname' => 'Anna global'],
		]);

		self::assertSame('Anna im Team', $members[0]['displayName']);
	}

	public function testRoomLocalMemberAvatarWinsOverGlobalProfile(): void {
		$members = $this->mapper->members([
			$this->member('@ct_anna:chat.church.tools', 'Anna', 'mxc://chat.church.tools/local-avatar'),
		], [
			'@ct_anna:chat.church.tools' => ['avatar_url' => 'mxc://chat.church.tools/global-avatar'],
		]);

		self::assertSame('mxc://chat.church.tools/local-avatar', $members[0]['avatarUrl']);
	}

	public function testGlobalProfileAvatarIsUsedAsFallback(): void {
		$members = $this->mapper->members([
			$this->member('@ct_anna:chat.church.tools', 'Anna'),
		], [
			'@ct_anna:chat.church.tools' => ['avatar_url' => 'mxc://chat.church.tools/global-avatar'],
		]);

		self::assertSame('mxc://chat.church.tools/global-avatar', $members[0]['avatarUrl']);
	}

	public function testDirectRoomUsesOtherMembersName(): void {
		$members = $this->mapper->members([
			$this->member('@ct_me:chat.church.tools', 'Samuel'),
			$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt'),
		]);
		$room = $this->mapper->room(
			'!direct:chat.church.tools',
			$this->roomState([['type' => 'm.room.name', 'content' => ['name' => 'Wrong direct name']]]),
			['@ct_anna:chat.church.tools' => ['!direct:chat.church.tools']],
			'@ct_me:chat.church.tools',
			$members,
		);

		self::assertSame('direct', $room['kind']);
		self::assertSame('Anna Schmidt', $room['name']);
	}

	public function testDirectRoomUsesOtherMembersAvatar(): void {
		$members = $this->mapper->members([
			$this->member('@ct_me:chat.church.tools', 'Samuel', 'mxc://chat.church.tools/me'),
			$this->member('@ct_anna:chat.church.tools', 'Anna', 'mxc://chat.church.tools/anna'),
		]);
		$room = $this->mapper->room(
			'!direct:chat.church.tools',
			$this->roomState([['type' => 'm.room.avatar', 'content' => ['url' => 'mxc://chat.church.tools/room']]]),
			['@ct_anna:chat.church.tools' => ['!direct:chat.church.tools']],
			'@ct_me:chat.church.tools',
			$members,
		);

		self::assertSame('mxc://chat.church.tools/anna', $room['avatarUrl']);
	}

	public function testGroupRoomUsesRoomAvatar(): void {
		$room = $this->mapper->room(
			'!group:chat.church.tools',
			$this->roomState([['type' => 'm.room.avatar', 'content' => ['url' => 'mxc://chat.church.tools/group']]]),
			[],
			'@ct_me:chat.church.tools',
			[],
		);

		self::assertSame('group', $room['kind']);
		self::assertSame('mxc://chat.church.tools/group', $room['avatarUrl']);
	}

	public function testGroupNameFallsBackFromNameToAliasAndMembers(): void {
		$members = $this->mapper->members([$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt')]);

		$named = $this->mapper->room('!named:test', $this->roomState([
			['type' => 'm.room.name', 'content' => ['name' => 'Worship Team']],
		]), [], '@ct_me:test', $members);
		$aliased = $this->mapper->room('!alias:test', $this->roomState([
			['type' => 'm.room.canonical_alias', 'content' => ['alias' => '#worship:test']],
		]), [], '@ct_me:test', $members);
		$fallback = $this->mapper->room('!members:test', $this->roomState([]), [], '@ct_me:test', $members);

		self::assertSame('Worship Team', $named['name']);
		self::assertSame('#worship:test', $aliased['name']);
		self::assertSame('Anna Schmidt', $fallback['name']);
	}

	public function testMissingProfileFallsBackWithoutFailing(): void {
		$members = $this->mapper->members([$this->member('@ct_anna_schmidt:chat.church.tools', '')]);

		self::assertSame('anna schmidt', $members[0]['displayName']);
	}

	public function testMessagesContainResolvedSenderMetadata(): void {
		$members = $this->mapper->members([
			$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt', 'mxc://chat.church.tools/anna'),
		]);
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$message',
			'sender' => '@ct_anna:chat.church.tools',
			'origin_server_ts' => 123,
			'content' => ['msgtype' => 'm.text', 'body' => 'Hallo'],
		]], $members);

		self::assertSame('Anna Schmidt', $messages[0]['senderName']);
		self::assertSame('@ct_anna:chat.church.tools', $messages[0]['sender']);
		self::assertSame('mxc://chat.church.tools/anna', $messages[0]['senderAvatarUrl']);
	}

	public function testImageMessageContainsAttachmentMetadata(): void {
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$image',
			'sender' => '@ct_anna:chat.church.tools',
			'origin_server_ts' => 123,
			'content' => [
				'msgtype' => 'm.image',
				'body' => 'team-photo.webp',
				'url' => 'mxc://chat.church.tools/team-photo',
				'info' => ['mimetype' => 'image/webp', 'size' => 1234],
			],
		]], []);

		self::assertSame('image', $messages[0]['attachment']['kind']);
		self::assertSame('mxc://chat.church.tools/team-photo', $messages[0]['attachment']['mxcUrl']);
		self::assertSame('team-photo.webp', $messages[0]['attachment']['filename']);
		self::assertSame('image/webp', $messages[0]['attachment']['mimeType']);
		self::assertSame(1234, $messages[0]['attachment']['size']);
	}

	/** @return array<string,mixed> */
	private function member(string $userId, string $displayName, ?string $avatarUrl = null): array {
		$content = ['membership' => 'join', 'displayname' => $displayName];
		if ($avatarUrl !== null) {
			$content['avatar_url'] = $avatarUrl;
		}
		return [
			'type' => 'm.room.member',
			'state_key' => $userId,
			'content' => $content,
		];
	}

	/** @param list<array<string,mixed>> $events @return array<string,mixed> */
	private function roomState(array $events): array {
		return [
			'state' => ['events' => $events],
			'timeline' => ['events' => []],
			'summary' => ['m.joined_member_count' => 2],
			'unread_notifications' => [],
		];
	}
}
