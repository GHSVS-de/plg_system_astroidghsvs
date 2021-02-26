const fse = require('fs-extra');
const util = require("util");
const rimRaf = util.promisify(require("rimraf"));

const Manifest = "./package/astroidghsvs.xml";

const {
	author,
	creationDate,
	copyright,
	filename,
	name,
	version,
	licenseLong,
	minimumPhp,
	maximumPhp,
	minimumJoomla,
	maximumJoomla,
	allowDowngrades,
} = require("./package.json");

const program = require('commander');

program
  .version(version)
  .on('--help', () => {
    // eslint-disable-next-line no-console
    console.log(`Version: ${version}`);
    process.exit(0);
  })
  .parse(process.argv);

const Program = program.opts();

(async function exec()
{
	const firstCleanOuts = [
		`./package`,
		`./dist`,
		"./src/versions-installed"
	];

	for (const file of firstCleanOuts)
	{
		await rimRaf(file).then(
			answer => console.log(`rimrafed: ${file}.`)
		);
	}

	await fse.copy(
		"./package-lock.json",
		"./src/versions-installed/npm_package-lock.json"
	).then(
		answer => console.log(`Copied ./package-lock.json.`)
	);

	await fse.copy(
		"./src/vendor/composer/installed.json",
		"./src/versions-installed/composer_installed.json"
		// ,
		// {overwrite:false, errorOnExist:true}
	);

	await rimRaf('./src/vendor/bin').then(
		answer => console.log(`rimrafed: ./src/vendor/bin`)
	);

	await rimRaf('./src/vendor/scssphp/scssphp/bin').then(
		answer => console.log(`rimrafed: /src/vendor/scssphp/scssphp/bin`)
	);

	// Copy and create new work dir.
	await fse.copy("./src", "./package"
	).then(
		answer => console.log(`Copied ./src to ./package.`)
	);

	// Create new dist dir.
	if (!(await fse.exists("./dist")))
	{
    	await fse.mkdir("./dist"
		).then(
			answer => console.log(`Created ./dist.`)
		);
  	}

	let xml = await fse.readFile(Manifest, { encoding: "utf8" });
	xml = xml.replace(/{{name}}/g, name);
	xml = xml.replace(/{{nameUpper}}/g, name.toUpperCase());
	xml = xml.replace(/{{authorName}}/g, author.name);
	xml = xml.replace(/{{creationDate}}/g, creationDate);
	xml = xml.replace(/{{copyright}}/g, copyright);
	xml = xml.replace(/{{licenseLong}}/g, licenseLong);
	xml = xml.replace(/{{authorUrl}}/g, author.url);
	xml = xml.replace(/{{version}}/g, version);
	xml = xml.replace(/{{minimumPhp}}/g, minimumPhp);
	xml = xml.replace(/{{maximumPhp}}/g, maximumPhp);
	xml = xml.replace(/{{minimumJoomla}}/g, minimumJoomla);
	xml = xml.replace(/{{maximumJoomla}}/g, maximumJoomla);
	xml = xml.replace(/{{allowDowngrades}}/g, allowDowngrades);
	xml = xml.replace(/{{filename}}/g, filename);

	await fse.writeFile(Manifest, xml, { encoding: "utf8" }
	).then(
		answer => console.log(`Replaced entries in ${Manifest}.`)
	);;

	// HOUSE CLEANING

	fse.unlinkSync("./package/composer.json");
	fse.unlinkSync("./package/composer.lock");

	// Package it
	const zip = new (require("adm-zip"))();
	zip.addLocalFolder("package", false);
	zip.writeZip(`dist/${name}-${version}.zip`);
})();
