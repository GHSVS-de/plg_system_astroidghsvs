# WORK IN PROGRESS!

# plg_system_astroidghsvs

Don't use it if you don't need it. Needs some skills and knowledge.

Helper plugin for ghsvs.de for Astroid Framework templates (since 2.4.7).
And other templates that want to use the on-the-fly feature to compile SCSS via scssphp/scssphp library.

1) Compile SCSS with "scssphp/scssphp" library packed with this plugin instead of the library that is packed with Astroid Framework. That library was not up-to-date enough for my needs.

2) Create .css and .min.css files. Create sourcemaps.

3) Helps to compile SCSS independently of the Astroid framework. Separate SCSS folders, separate SCSS structure. However, the template index.php code must be prepared accordingly (special overrides and so on).

4) Can be used for other templates as well, just to have the feature of having SCSSPhp available in latest version. But also for on-the-fly compilation. The template index.php code must be prepared accordingly (special overrides and so on).

## npm/composer. Create new Joomla extension installation package

- Only tested with WSL 1/Win10 64

- Clone repository into your server environment.

- `cd /mnt/z/git-kram/plg_system_astroidghsvs`

- Check/edit `/package.json` and add plugin `version` and further settings like `minimumPhp` and so on. Will be copied during build process into manifest XML.

- Check also versions of dependencies, devDependencies in `/package.json`: `npm run g-npm-update-check` and `npm run g-ncu-override-json`

- - **You shouldn't bump composer to 7+ as long build.js is not fixed accordingly.** See https://github.com/GHSVS-de/plg_system_bs3ghsvs/blob/2021.01.15/build.js#L24-L38

- Check/adapt versions in `/src/composer.json`. Something to bump in `vendor/`?

```
cd src/

composer outdated

OR

composer show -l
```
- both commands accept the parameter `--direct` to show only direct dependencies in the listing

### Automatically "download" PHP packages into `/src/vendor/`

```
cd src/
composer install
```

OR
(whenever libraries in vendor/ shall be updated)

```
cd src/
composer update
```

### Automatically "download" JS/CSS packages into `/node_modules`

- I you want to check first: `npm run g-npm-update-check`

- If you want to adapt package.json automatically first: `npm run g-ncu-override-json`


- `cd ..`
- `npm install`

OR

- `npm update`

### Build new Joomla package ZIP.

- <strike>May be necessary: `nvm use 12` or `nvm use 13` to get rid of f'ing messages of NodeJs 14+ that nobody understands but the Node creators and JS professors.</strike>

- `node build.js`

#####
- New, installable ZIP is in `/dist/` afterwards.

- FYI: Packed files for this ZIP can be seen in `/package/`.

#### For Joomla update server
- Create new release with new tag.
- Get download link for new `dist/plg_blahaba_blubber...zip` **inside new tag branch** and add to release description and update the update XML.
