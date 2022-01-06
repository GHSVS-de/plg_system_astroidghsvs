#!/usr/bin/env node

'use strict'

const fse = require('fs-extra');
const util = require("util");
const rimRaf = util.promisify(require("rimraf"));
const pc = require('picocolors');
const crypto = require('crypto');

// Activate for "module.exports.unminifyCss"!
// const recursive = require("recursive-readdir");
// const unminifyCss = require('cssunminifier').unminify;

module.exports.cleanOut = async (cleanOuts) =>
{
	for (const file of cleanOuts)
	{
		await rimRaf(file).then(
			answer => console.log(pc.red(pc.bold(`rimRafed "${file}".`)))
		).catch(error => console.error('Error ' + error));
	}
}

// Digest sha256, sha384 or sha512.
module.exports.getChecksum = async (path, Digest) =>
{
	if (!Digest)
	{
		Digest = 'sha256';
	}

  return new Promise(function (resolve, reject)
	{
    const hash = crypto.createHash(Digest);
    const input = fse.createReadStream(path);

    input.on('error', reject);
    input.on('data', function (chunk)
		{
      hash.update(chunk);
    });

    input.on('close', function ()
		{
      resolve(hash.digest('hex'));
    });
  });
}

// Find version string in file. E.g. 'scssphp/scssphp'
module.exports.findVersionSub = async (packagesFile, packageName) =>
{
	console.log(pc.magenta(pc.bold(
	`Search versionSub of package "${packageName}" in "${packagesFile}".`)));

	let foundVersion = '';
	const {packages} = require(packagesFile);

	await packages.forEach((Package) =>
	{
		if (Package.name === packageName)
		{
			foundVersion = Package.version_normalized;
			return false;
		}
	});

	return foundVersion;
}

// Simple. Find version string in file package.json
module.exports.findVersionSubSimple = async (packagesFile, packageName) =>
{
	console.log(pc.magenta(pc.bold(
	`Search versionSub of package "${packageName}" in "${packagesFile}".`)));

	return require(packagesFile).version;
}

// Unminify recursive. All *.min.css to *.css
// Usage in build.js: await helper.unminifyCss(folderPath);
module.exports.unminifyCss = async (folder) =>
{
	await recursive(folder).then(
		function(files) {
			const thisRegex = new RegExp('\.min\.css$');

			files.forEach((file) => {
				file = `./${file}`;

				if (thisRegex.test(file) && fse.existsSync(file)
					&& fse.lstatSync(file).isFile())
				{
					console.log(pc.magenta(pc.bold(`File to unminify: ${file}`)));
					let unminifiedFile = file.replace('.min.css', '.css');
					let code = fse.readFileSync(`${file}`).toString();
					code = unminifyCss(code);
					fse.writeFileSync(unminifiedFile, code, {encoding: "utf8"});
					console.log(pc.green(pc.bold(
						`Unminified file written: ${unminifiedFile}`))
					);
				}
			});
		},
		function(error) {
			console.error("something exploded", error);
		}
	);
}
