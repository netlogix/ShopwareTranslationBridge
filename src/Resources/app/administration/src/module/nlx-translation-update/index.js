import template from './nlx-translation-update.html.twig'

const {Mixin} = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'nlxTranslationApiService'
    ],

    data() {
        return {
            componentIsBuilding: true,
            processes: false,
            processSuccess: false
        };
    },

    methods: {
        updateTranslation() {
            this.processes = true;

            this.nlxTranslationApiService
                .updateTranslation()
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('nlx-storefront-translation.nlx-translation-update.updateTranslation.success'),
                    });
                })
                .catch((e) => {
                    const error = e.response?.data?.error ?? 'error';
                    this.createNotificationError({
                        message: this.$tc('nlx-storefront-translation.nlx-translation-update.updateTranslation.' + error),
                    });
                    this.processSuccess = false;
                })
                .finally(() => {
                    this.processes = false;
                })
        },
        resetButton() {
            this.processSuccess = true;
        }
    }
}
