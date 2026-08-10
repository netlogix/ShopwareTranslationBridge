const {ApiService} = Shopware.Classes;

export default class NlxTranslationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/nlx-translation') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'nlxTranslationApiService';
    }

    updateTranslation() {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/update`,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => ApiService.handleResponse(response));
    }

    getProviders() {
        return this.httpClient
            .get(
                `${this.getApiBasePath()}/providers`,
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
