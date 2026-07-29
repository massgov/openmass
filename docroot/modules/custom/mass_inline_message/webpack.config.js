const path = require('path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
  mode: 'production',
  optimization: {
    minimize: true,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          format: {
            comments: false
          }
        },
        test: /\.js(\?.*)?$/i,
        extractComments: false
      })
    ],
    moduleIds: 'named'
  },
  entry: {
    path: path.resolve(__dirname, 'js/ckeditor5_plugins/mass_inline_message/src/index.js')
  },
  output: {
    path: path.resolve(__dirname, './js/build'),
    filename: 'mass_inline_message.js',
    library: ['CKEditor5', 'mass_inline_message'],
    libraryTarget: 'umd',
    libraryExport: 'default'
  },
  plugins: [
    new webpack.DllReferencePlugin({
      manifest: require('ckeditor5/build/ckeditor5-dll.manifest.json'),
      scope: 'ckeditor5/src',
      name: 'CKEditor5.dll'
    })
  ],
  module: {
    rules: [{test: /\.svg$/, use: 'raw-loader'}]
  },
  devtool: false
};
