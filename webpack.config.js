const path = require('path');
const basePath = __dirname;
const distPath = 'assets/build';
const webpackInitConfig = {
    mode: 'production',
    resolve: {
        extensions: ['.js']
    },
    entry: {
        app: ['./assets/js/admin-settings.js'],
    },
    output: {
        path: path.join(basePath, distPath),
        filename: '[name].js'
    }
};
module.exports = webpackInitConfig;