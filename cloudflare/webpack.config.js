const webpack = require('webpack');

module.exports = {
  entry: "./src/index.js",
  mode: "development",
  optimization: {
    minimize: false
  },
  performance: {
    hints: "warning"
  },
  plugins: [
    // Pull in the CDN verification token from the environment.
    // This will be added as a header to all origin requests to prevent
    // CDN bypass attacks. See scripts/cloudflare-deploy.
    new webpack.EnvironmentPlugin({
      'MASS_CDN_TOKEN': 'default'
    })
  ],
  output: {
    path: __dirname + "/dist",
    publicPath: "dist",
    filename: "worker.js"
  }
}
