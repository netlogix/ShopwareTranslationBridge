import TranslationApiService from './service/translationApi.Service';

const {Application} = Shopware;

Shopware.Component.register(
    'nlx-translation-update',
    () => import('./module/nlx-translation-update')
);

Shopware.Component.register(
    'nlx-translation-provider-select',
    () => import('./component/nlx-translation-provider-select')
);

Shopware.Component.register(
    'nlx-translation-provider-notice',
    () => import('./component/nlx-translation-provider-notice')
);

Shopware.Component.override(
    'sw-settings-cache-index',
    () => import('./overrride/module/sw-settings-cache-index')
);

Shopware.Component.override(
    'sw-settings-snippet-set-list',
    () => import('./overrride/module/sw-settings-snippet-set-list')
);

Shopware.Component.override(
    'sw-settings-snippet-list',
    () => import('./overrride/module/sw-settings-snippet-list')
);

Application.addServiceProvider(
    'nlxTranslationApiService',
    (container) => new TranslationApiService(
        Application.getContainer('init').httpClient,
        container.loginService
    )
);
