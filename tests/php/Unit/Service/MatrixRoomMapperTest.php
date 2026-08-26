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

	public function testSurfacesLimitedTimelineAndPrevBatch(): void {
		$members = $this->mapper->members([$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt')]);
		$room = $this->mapper->room(
			'!limited:chat.church.tools',
			[
				'state' => ['events' => []],
				'timeline' => [
					'events' => [],
					'limited' => true,
					'prev_batch' => '$prev:chat.church.tools',
				],
				'summary' => ['m.joined_member_count' => 2],
				'unread_notifications' => [],
			],
			[],
			'@ct_me:chat.church.tools',
			$members,
		);

		self::assertTrue($room['limited']);
		self::assertSame('$prev:chat.church.tools', $room['prevBatch']);
	}

	public function testMapsEphemeralTypingUsersAndReadReceipts(): void {
		$members = $this->mapper->members([
			$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt'),
			$this->member('@ct_ben:chat.church.tools', 'Ben Becker'),
		]);
		$room = $this->mapper->room(
			'!room:chat.church.tools',
			[
				'state' => ['events' => []],
				'timeline' => ['events' => []],
				'summary' => ['m.joined_member_count' => 2],
				'unread_notifications' => [],
				'ephemeral' => ['events' => [
					[
						'type' => 'm.typing',
						'content' => ['user_ids' => ['@ct_anna:chat.church.tools', '@ct_me:chat.church.tools']],
					],
					[
						'type' => 'm.receipt',
						'content' => [
							'$message:chat.church.tools' => [
								'm.read' => ['@ct_anna:chat.church.tools' => ['ts' => 1]],
							],
						],
					],
				]],
			],
			[],
			'@ct_me:chat.church.tools',
			$members,
		);

		self::assertSame([['id' => '@ct_anna:chat.church.tools', 'displayName' => 'Anna Schmidt']], $room['typingUsers']);
		self::assertSame(['@ct_anna:chat.church.tools' => '$message:chat.church.tools'], $room['readReceipts']);
	}

	public function testEphemeralFieldsAreEmptyWithoutEvents(): void {
		$room = $this->mapper->room('!room:chat.church.tools', $this->roomState([]), [], '@ct_me:chat.church.tools', []);

		self::assertSame([], $room['typingUsers']);
		self::assertSame([], $room['readReceipts']);
	}

	public function testChatRoomAliasFollowsUserDerivation(): void {
		self::assertSame(
			'#ctg_681f54e3-2eb7-40a4-84f0-eff8e8f05727:chat.church.tools',
			$this->mapper->chatRoomAlias('ctg', '681F54E3-2EB7-40A4-84F0-EFF8E8F05727', 'chat.church.tools'),
		);
	}

	public function testTracksOwnReactionsWithEventIds(): void {
		$events = [
			['type' => 'm.room.message', 'event_id' => '$msg', 'sender' => '@ct_anna:chat.church.tools', 'origin_server_ts' => 1, 'content' => ['msgtype' => 'm.text', 'body' => 'hello']],
			['type' => 'm.reaction', 'event_id' => '$r1', 'sender' => '@ct_me:chat.church.tools', 'origin_server_ts' => 2, 'content' => ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$msg', 'key' => '👍']]],
			['type' => 'm.reaction', 'event_id' => '$r2', 'sender' => '@ct_anna:chat.church.tools', 'origin_server_ts' => 3, 'content' => ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$msg', 'key' => '👍']]],
			['type' => 'm.reaction', 'event_id' => '$r3', 'sender' => '@ct_anna:chat.church.tools', 'origin_server_ts' => 4, 'content' => ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$msg', 'key' => '❤️']]],
		];

		$messages = $this->mapper->events($events, [], '@ct_me:chat.church.tools');

		self::assertCount(1, $messages);
		self::assertSame(['👍' => 2, '❤️' => 1], $messages[0]['reactions']);
		self::assertSame([['key' => '👍', 'eventId' => '$r1']], $messages[0]['ownReactions']);
	}

	public function testOwnReactionsAreEmptyWithoutCurrentUser(): void {
		$events = [
			['type' => 'm.room.message', 'event_id' => '$msg', 'sender' => '@ct_anna:chat.church.tools', 'origin_server_ts' => 1, 'content' => ['msgtype' => 'm.text', 'body' => 'hello']],
			['type' => 'm.reaction', 'event_id' => '$r1', 'sender' => '@ct_anna:chat.church.tools', 'origin_server_ts' => 2, 'content' => ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$msg', 'key' => '👍']]],
		];

		$messages = $this->mapper->events($events, []);

		self::assertSame([], $messages[0]['ownReactions']);
		self::assertSame(['👍' => 1], $messages[0]['reactions']);
	}

	public function testMatchChatsToRoomsByAliasAndName(): void {
		$chats = [
			['creator' => null, 'domainId' => 9, 'guid' => '681F54E3-2EB7-40A4-84F0-EFF8E8F05727', 'prefix' => 'ctg', 'roomname' => 'Technik', 'status' => 'STARTED'],
			['creator' => null, 'domainId' => 10, 'guid' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'prefix' => 'cte', 'roomname' => 'Event ABC', 'status' => 'STARTED'],
		];
		$rooms = [
			['roomId' => '!byAlias:test', 'state' => [
				'm.room.canonical_alias' => ['alias' => '#ctg_681f54e3-2eb7-40a4-84f0-eff8e8f05727:chat.church.tools'],
			]],
			['roomId' => '!byName:test', 'state' => [
				'm.room.name' => ['name' => 'Event ABC'],
			]],
		];

		$matches = $this->mapper->matchChatsToRooms($chats, $rooms, 'chat.church.tools');

		self::assertCount(2, $matches);
		self::assertSame('!byAlias:test', $matches[0]['roomId']);
		self::assertSame('alias', $matches[0]['confidence']);
		self::assertSame('!byName:test', $matches[1]['roomId']);
		self::assertSame('name', $matches[1]['confidence']);
	}

	public function testEditReplacesBodyAndMarksMessageAsEdited(): void {
		$members = $this->mapper->members([$this->member('@ct_anna:chat.church.tools', 'Anna Schmidt')]);
		$events = [
			[
				'type' => 'm.room.message',
				'event_id' => '$original',
				'sender' => '@ct_anna:chat.church.tools',
				'origin_server_ts' => 100,
				'content' => ['msgtype' => 'm.text', 'body' => 'original'],
			],
			[
				'type' => 'm.room.message',
				'event_id' => '$edit',
				'sender' => '@ct_anna:chat.church.tools',
				'origin_server_ts' => 200,
				'content' => [
					'msgtype' => 'm.text',
					'body' => '* new body',
					'm.new_content' => ['msgtype' => 'm.text', 'body' => 'new body'],
					'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$original'],
				],
			],
		];

		$messages = $this->mapper->events($events, $members);

		self::assertCount(1, $messages);
		self::assertSame('new body', $messages[0]['body']);
		self::assertTrue($messages[0]['edited']);
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

	public function testStripsRichReplyFallbackFromReplyBody(): void {
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$reply',
			'sender' => '@ct_ben:chat.church.tools',
			'origin_server_ts' => 200,
			'content' => [
				'msgtype' => 'm.text',
				'body' => "> <@ct_anna:chat.church.tools> Original message text\n\nMy reply here",
				'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$original']],
			],
		]], []);

		self::assertSame('My reply here', $messages[0]['body']);
		self::assertSame(['m.in_reply_to' => ['event_id' => '$original']], $messages[0]['relatesTo']);
	}

	public function testStripsMultiLineRichReplyFallback(): void {
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$reply',
			'sender' => '@ct_ben:chat.church.tools',
			'origin_server_ts' => 200,
			'content' => [
				'msgtype' => 'm.text',
				'body' => "> line one\n> line two\n> \n\nReply without quote prefix",
				'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$original']],
			],
		]], []);

		self::assertSame('Reply without quote prefix', $messages[0]['body']);
	}

	public function testReplyFallbackPreservesUtf8ContainingAnNELByte(): void {
		$reply = "Reply with Ņ";
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$reply',
			'sender' => '@ct_ben:chat.church.tools',
			'origin_server_ts' => 200,
			'content' => [
				'msgtype' => 'm.text',
				'body' => "> <@ct_anna:chat.church.tools> Original\n\n" . $reply,
				'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$original']],
			],
		]], []);

		self::assertSame($reply, $messages[0]['body']);
		self::assertIsString(json_encode($messages, JSON_THROW_ON_ERROR));
	}

	public function testKeepsBlockquoteStyleBodyWhenNotAReply(): void {
		$messages = $this->mapper->events([[
			'type' => 'm.room.message',
			'event_id' => '$quote',
			'sender' => '@ct_anna:chat.church.tools',
			'origin_server_ts' => 200,
			'content' => ['msgtype' => 'm.text', 'body' => "> A real blockquote\n\nStill here"],
		]], []);

		self::assertSame("> A real blockquote\n\nStill here", $messages[0]['body']);
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
