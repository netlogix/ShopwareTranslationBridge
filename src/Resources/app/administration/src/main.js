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

Shopware.Component.override(
    'sw-settings-cache-index',
    () => import('./overrride/module/sw-settings-cache-index')
);

Application.addServiceProvider(
    'nlxTranslationApiService',
    (container) => new TranslationApiService(
        Application.getContainer('init').httpClient,
        container.loginService
    )
);
