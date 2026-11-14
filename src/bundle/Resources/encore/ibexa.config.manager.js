const path = require('path');

module.exports = (ibexaConfig, ibexaConfigManager) => {
    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-js',
        newItems: [path.resolve(__dirname, '../public/admin/js/ai-suggest.js')],
    });

    ibexaConfigManager.add({
        ibexaConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-css',
        newItems: [
            path.resolve(__dirname, '../public/admin/scss/_ai-suggest-trigger.scss'),
            path.resolve(__dirname, '../public/admin/suggest/suggest.scss'),
        ],
    });
};
