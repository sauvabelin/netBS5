const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('../netBS/core/CoreBundle/Resources/public/dist/')
    .setPublicPath('/bundles/netbscore/dist')
    .setManifestKeyPrefix('dist')
    .addEntry('app', './assets/js/app.js')
    .enableSassLoader()
    .autoProvidejQuery()
    .enableSourceMaps(!Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();
