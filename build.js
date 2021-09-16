const fse = require('fs-extra');
const path = require('path');
const chalk = require('chalk');
const replaceXml = require('./build/replaceXml.js');
const helper = require('./build/helper.js');

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
	];

	await helper.cleanOut(cleanOuts);

	versionSub = await helper.findVersionSub (
		path.join(__dirname, vendorPath, `composer/installed.json`),
			'scssphp/scssphp');
	console.log(chalk.magentaBright(`versionSub identified as: "${versionSub}"`));

	await console.log(chalk.redBright(`Be patient! Composer copy actions!`));
	await fse.copy(`${vendorPath}`, `./package/vendor`
	).then(
		answer => console.log(chalk.yellowBright(
			`Copied "_composer/vendor" to "./package/vendor".`))
	);

	await fse.copy("./src", "./package"
	).then(
		answer => console.log(chalk.yellowBright(`Copied "./src" to "./package".`))
	);

	if (!(await fse.exists("./dist")))
	{
    	await fse.mkdir("./dist"
		).then(
			answer => console.log(chalk.yellowBright(`Created "./dist".`))
		);
  }

	const zipFilename = `${name}-${version}_${versionSub}.zip`;

	await replaceXml.main(Manifest, zipFilename);
	await fse.copy(`${Manifest}`, `./dist/${manifestFileName}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied "${manifestFileName}" to "./dist".`))
	);

	cleanOuts = [
		`${__dirname}/package/vendor/bin`,
		`${__dirname}/package/vendor/scssphp/scssphp/bin`,
	];

	await helper.cleanOut(cleanOuts);

	// Create zip file and detect checksum then.
	const zipFilePath = `./dist/${zipFilename}`;

	const zip = new (require('adm-zip'))();
	zip.addLocalFolder("package", false);
	await zip.writeZip(`${zipFilePath}`);
	console.log(chalk.cyanBright(chalk.bgRed(
		`"./dist/${zipFilename}" written.`)));

	const Digest = 'sha256'; //sha384, sha512
	const checksum = await helper.getChecksum(zipFilePath, Digest)
  .then(
		hash => {
			const tag = `<${Digest}>${hash}</${Digest}>`;
			console.log(chalk.greenBright(`Checksum tag is: ${tag}`));
			return tag;
		}
	)
	.catch(error => {
		console.log(error);
		console.log(chalk.redBright(`Error while checksum creation. I won't set one!`));
		return '';
	});

	let xmlFile = 'update.xml';
	await fse.copy(`./${xmlFile}`, `./dist/${xmlFile}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied "${xmlFile}" to ./dist.`))
	);
	await replaceXml.main(`${__dirname}/dist/${xmlFile}`, zipFilename, checksum);

	xmlFile = 'changelog.xml';
	await fse.copy(`./${xmlFile}`, `./dist/${xmlFile}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied "${xmlFile}" to ./dist.`))
	);
	await replaceXml.main(`${__dirname}/dist/${xmlFile}`, zipFilename, checksum);

	xmlFile = 'release.txt';
	await fse.copy(`./${xmlFile}`, `./dist/${xmlFile}`).then(
		answer => console.log(chalk.yellowBright(
			`Copied "${xmlFile}" to ./dist.`))
	);
	await replaceXml.main(`${__dirname}/dist/${xmlFile}`, zipFilename, checksum);

	cleanOuts = [
		`./package`,
	];
	await helper.cleanOut(cleanOuts).then(
		answer => console.log(chalk.cyanBright(chalk.bgRed(
			`Finished. Good bye!`)))
	);
})();
