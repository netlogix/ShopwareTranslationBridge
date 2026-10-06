import template from './nlx-translation-provider-notice.html.twig';

export default {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            providerName: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            try {
                const config = await this.systemConfigApiService.getValues('ShopwareTranslationBridge.config');
                this.providerName = config['ShopwareTranslationBridge.config.keyProvider'] || null;
            } catch {
                this.providerName = null;
            }
        },
    },
};
