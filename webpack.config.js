const defaultConfigs = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

// @wordpress/scripts v27+ exports an array [mainConfig, rtlConfig]
const configs = Array.isArray(defaultConfigs)
	? defaultConfigs
	: [defaultConfigs];
const mainConfig = configs[0];

const creditsManagerConfig = {
	...mainConfig,
	entry: {
		'credits-manager/index': path.resolve(
			__dirname,
			'src/credits-manager/index.js'
		),
	},
	output: {
		...mainConfig.output,
		path: path.resolve(__dirname, 'build'),
	},
};

module.exports = [...configs, creditsManagerConfig];
