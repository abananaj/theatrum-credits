const path = require('path');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const MiniCSSExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
	mode: process.env.NODE_ENV === 'development' ? 'development' : 'production',
	entry: {
		index: path.resolve(__dirname, 'src/credits-manager/index.js'),
	},
	output: {
		path: path.resolve(__dirname, 'build/credits-manager'),
		filename: '[name].js',
	},
	resolve: {
		extensions: ['.js', '.jsx'],
	},
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@wordpress/babel-preset-default'],
					},
				},
			},
			{
				test: /\.scss$/,
				use: [
					MiniCSSExtractPlugin.loader,
					'css-loader',
					{
						loader: 'sass-loader',
						options: { sassOptions: { quietDeps: true } },
					},
				],
			},
		],
	},
	plugins: [
		new DependencyExtractionWebpackPlugin(),
		new MiniCSSExtractPlugin({ filename: '[name].css' }),
	],
};
