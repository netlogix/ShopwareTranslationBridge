const {ApiService} = Shopware.Classes;

export default class NlxTranslationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/nlx-translation') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'nlxNeosContentApiService';
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
}
