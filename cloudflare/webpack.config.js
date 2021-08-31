const webpack = require('webpack');
const NodePolyfillPlugin = require("node-polyfill-webpack-plugin")

module.exports = {
  entry: "./src/index.js",
  mode: "production",
  optimization: {
    minimize: false
  },
  performance: {
    hints: "warning"
  },
  plugins: [
    new NodePolyfillPlugin(),
    // Pull in the CDN verification token from the environment.
    // This will be added as a header to all origin requests to prevent
    // CDN bypass attacks. See scripts/cloudflare-deploy.
    new webpack.DefinePlugin({
      'process.env.MASS_CDN_TOKEN': JSON.stringify(process.env.MASS_CDN_TOKEN),
    })
  ],
  output: {
    path: __dirname + "/dist",
    publicPath: "dist",
    filename: "worker.js"
  }
}
