#!/usr/bin/env node

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

const semverPattern = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/

function fail(message) {
	console.error(`Version check failed: ${message}`)
	process.exit(1)
}

function readManifestVersion(contents, source) {
	const match = contents.match(/<version>\s*([^<\s]+)\s*<\/version>/)
	if (!match) fail(`could not read <version> from ${source}`)
	return match[1]
}

function validate(version, source) {
	if (!semverPattern.test(version)) {
		fail(`${source} version "${version}" must be major.minor.patch`)
	}
}

function compare(left, right) {
	const leftParts = left.split('.').map(Number)
	const rightParts = right.split('.').map(Number)
	for (let index = 0; index < leftParts.length; index += 1) {
		if (leftParts[index] !== rightParts[index]) return leftParts[index] - rightParts[index]
	}
	return 0
}

function git(...args) {
	try {
		return execFileSync('git', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim()
	} catch {
		return null
	}
}

function versionAt(ref) {
	const contents = git('show', `${ref}:appinfo/info.xml`)
	if (contents === null) return null
	const version = readManifestVersion(contents, `${ref}:appinfo/info.xml`)
	validate(version, `${ref}:appinfo/info.xml`)
	return version
}

function latestTagVersion() {
	const tags = git('tag', '--list', 'v*', '--sort=-v:refname')
	if (!tags) return null
	for (const tag of tags.split('\n')) {
		const version = tag.slice(1)
		if (semverPattern.test(version)) return { tag, version }
	}
	return null
}

const args = process.argv.slice(2)
if (args.length !== 0 && (args.length !== 2 || args[0] !== '--base-ref' || !args[1])) {
	fail('usage: node scripts/check-version.mjs [--base-ref <ref>]')
}
const baseRef = args.length === 0 ? null : args[1]

const manifestVersion = readManifestVersion(readFileSync('appinfo/info.xml', 'utf8'), 'appinfo/info.xml')
const packageVersion = JSON.parse(readFileSync('package.json', 'utf8')).version
validate(manifestVersion, 'appinfo/info.xml')
validate(packageVersion, 'package.json')

if (manifestVersion !== packageVersion) {
	fail(`appinfo/info.xml (${manifestVersion}) and package.json (${packageVersion}) must match`)
}

if (baseRef) {
	const baseVersion = versionAt(baseRef)
	if (baseVersion && compare(manifestVersion, baseVersion) <= 0) {
		fail(`${manifestVersion} must be higher than ${baseRef} (${baseVersion})`)
	}
}

const latestTag = latestTagVersion()
if (latestTag && compare(manifestVersion, latestTag.version) <= 0) {
	fail(`${manifestVersion} must be higher than latest tag ${latestTag.tag}`)
}

console.log(`Version ${manifestVersion} is valid${baseRef ? ` and higher than ${baseRef}` : ''}${latestTag ? ` and ${latestTag.tag}` : ''}.`)
