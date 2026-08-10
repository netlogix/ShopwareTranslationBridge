import template from './nlx-translation-provider-select.html.twig';

export default {
    template,

    inject: ['nlxTranslationApiService'],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },
        label: {
            required: false,
            default: null,
        },
        helpText: {
            required: false,
            default: null,
        },
        error: {
            type: Object,
            required: false,
            default: null,
        },
    },

    emits: ['update:value'],

    data() {
        return {
            isLoading: false,
            options: [],
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                const response = await this.nlxTranslationApiService.getProviders();
                this.options = response.options ?? [];
            } finally {
                this.isLoading = false;
            }
        },

        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
