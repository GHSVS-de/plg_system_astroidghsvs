#!/usr/bin/env node
const path = require('path');

/* Configure START */
const pathBuildKram = path.resolve("../buildKramGhsvs");
const updateXml = `${pathBuildKram}/build/update.xml`;
const changelogXml = `${pathBuildKram}/build/changelog.xml`;
const releaseTxt = `${pathBuildKram}/build/release.txt`;
/* Configure END */

const replaceXml = require(`${pathBuildKram}/build/replaceXml.js`);
const helper = require(`${pathBuildKram}/build/helper.js`);

const pc = require(`${pathBuildKram}/node_modules/picocolors`);
//const fse = require(`${pathBuildKram}/node_modules/fs-extra`);

let replaceXmlOptions = {
	"xmlFile": '',
	"zipFilename": '',
	"checksum": '',
	"dirname": __dirname,
	"jsonString": '',
	"versionSub": ''
};
let zipOptions = {};
let from = "";
let to = "";

const {
	name,
	filename,
	version,
} = require("./package.json");

const manifestFileName = `${filename}.xml`;
const Manifest = `${__dirname}/package/${manifestFileName}`;
const vendorPath = `./_composer/vendor`;
let versionSub = '';

(async function exec()
{
	let cleanOuts = [
		`./package`,
		`./dist`,
		`./src/versions-installed`,
		// Leads again and again to conflicts in SyncBack and elsewhere.
		// "Das System kann auf die Datei nicht zugreifen"
		`${vendorPath}/bin`,
		`${vendorPath}/scssphp/scssphp/bin`,
	];
	await helper.cleanOut(cleanOuts);

	versionSub = await helper.findVersionSub (
		path.join(__dirname, vendorPath, `composer/installed.json`),
			'scssphp/scssphp');
	console.log(pc.magenta(pc.bold(`versionSub identified as: "${versionSub}"`)));

	replaceXmlOptions.versionSub = versionSub;

	await console.log(pc.red(pc.bold(`Be patient! Composer copy actions!`)));

	from = vendorPath;
	to = `./package/vendor`;
	await helper.copy(from, to)

	from = `./src`;
	to = `./package`;
	await helper.copy(from, to)

	await helper.mkdir('./dist');

	const zipFilename = `${name}-${version}_${versionSub}.zip`;

	replaceXmlOptions.xmlFile = Manifest
	replaceXmlOptions.zipFilename = zipFilename

	await replaceXml.main(replaceXmlOptions);

	from = Manifest;
	to = `./dist/${manifestFileName}`;
	await helper.copy(from, to)

	cleanOuts = [
		`${__dirname}/package/vendor/bin`,
		`${__dirname}/package/vendor/scssphp/scssphp/bin`,
	];
	await helper.cleanOut(cleanOuts);

	// ## Create zip file and detect checksum then.
	const zipFilePath = path.resolve(`./dist/${zipFilename}`);

	zipOptions = {
		"source": path.resolve("package"),
		"target": zipFilePath
	};
	await helper.zip(zipOptions)

	replaceXmlOptions.checksum = await helper._getChecksum(zipFilePath);

	// Bei diesen werden zuerst Vorlagen nach dist/ kopiert und dort erst "replaced".
	for (const file of [updateXml, changelogXml, releaseTxt])
	{
		from = file;
		to = `./dist/${path.win32.basename(file)}`;
		await helper.copy(from, to)

		replaceXmlOptions.xmlFile = path.resolve(to);
		await replaceXml.main(replaceXmlOptions);
	}

	cleanOuts = [
		`./package`,
	];
	await helper.cleanOut(cleanOuts).then(
		answer => console.log(pc.cyan(pc.bold(pc.bgRed(
			`Finished. Good bye!`))))
	);
})();
