<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'settings#save', 'url' => '/api/settings', 'verb' => 'POST'],
		['name' => 'settings#destroy', 'url' => '/api/settings', 'verb' => 'DELETE'],
		['name' => 'admin_settings#get', 'url' => '/api/admin/settings', 'verb' => 'GET'],
		['name' => 'admin_settings#save', 'url' => '/api/admin/settings', 'verb' => 'POST'],
		['name' => 'chat#status', 'url' => '/api/status', 'verb' => 'GET'],
		['name' => 'avatar#thumbnail', 'url' => '/api/avatar', 'verb' => 'GET'],
		['name' => 'media#thumbnail', 'url' => '/api/media/thumbnail', 'verb' => 'GET'],
		['name' => 'media#view', 'url' => '/api/media/view', 'verb' => 'GET'],
		['name' => 'media#download', 'url' => '/api/media/download', 'verb' => 'GET'],
		['name' => 'media#save', 'url' => '/api/media/save', 'verb' => 'POST'],
		['name' => 'chat#rooms', 'url' => '/api/rooms', 'verb' => 'GET'],
		['name' => 'chat#searchConversations', 'url' => '/api/search', 'verb' => 'GET'],
		['name' => 'chat#searchPersons', 'url' => '/api/persons', 'verb' => 'GET'],
		['name' => 'chat#startDirect', 'url' => '/api/direct-chats', 'verb' => 'POST'],
		['name' => 'chat#details', 'url' => '/api/rooms/{roomId}/details', 'verb' => 'GET'],
		['name' => 'chat#searchMessages', 'url' => '/api/rooms/{roomId}/search', 'verb' => 'GET'],
		['name' => 'chat#messages', 'url' => '/api/rooms/{roomId}/messages', 'verb' => 'GET'],
		['name' => 'chat#message', 'url' => '/api/rooms/{roomId}/messages/{eventId}', 'verb' => 'GET'],
		['name' => 'chat#send', 'url' => '/api/rooms/{roomId}/messages', 'verb' => 'POST'],
		['name' => 'chat#react', 'url' => '/api/rooms/{roomId}/messages/{eventId}/reactions', 'verb' => 'POST'],
		['name' => 'chat#edit', 'url' => '/api/rooms/{roomId}/messages/{eventId}', 'verb' => 'PUT'],
		['name' => 'chat#redact', 'url' => '/api/rooms/{roomId}/messages/{eventId}/redact', 'verb' => 'POST'],
		['name' => 'chat#setFullyRead', 'url' => '/api/rooms/{roomId}/read-marker', 'verb' => 'POST'],
		['name' => 'chat#sync', 'url' => '/api/sync', 'verb' => 'GET'],
	],
];
