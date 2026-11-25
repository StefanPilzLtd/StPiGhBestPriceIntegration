import template from './stpi-gh-bestprice.html.twig';
import './stpi-gh-bestprice.scss';
const { Application, Component, Module, Mixin } = Shopware;
const empty = () => ({
    name: '',
    salesChannelId: null,
    domainId: null,
    countryId: null,
    merchantId: '',
    enabled: false,
    productStreamId: null,
    productIds: [],
    categoryIds: [],
    manufacturerIds: [],
    tagIds: [],
    excludedProductIds: [],
    mapping: {},
    intervalMinutes: 60,
    shippingFallbackConfirmed: false,
    inStock: 'Σε απόθεμα',
    outOfStock: 'Κατόπιν παραγγελίας',
    trackingEnabled: false,
    trackingKey: '',
});
Application.addServiceProvider('stpiGhBestpriceApi', (container) => {
    const http = Application.getContainer('init').httpClient;
    return {
        request(method, path, data) {
            return http
                .request({
                    method,
                    url: `_action/stpi-gh-bestprice${path}`,
                    headers: {
                        Authorization: `Bearer ${container.loginService.getToken()}`,
                    },
                    data,
                })
                .then((response) => response.data);
        },
    };
});
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'marketing',
    key: 'stpi_gh_bestprice',
    roles: {
        viewer: { privileges: ['stpi_gh_bestprice.viewer'], dependencies: [] },
        editor: {
            privileges: [
                'stpi_gh_bestprice.editor',
                'product:read',
                'category:read',
                'product_manufacturer:read',
                'tag:read',
                'product_stream:read',
            ],
            dependencies: ['stpi_gh_bestprice.viewer'],
        },
        generator: {
            privileges: ['stpi_gh_bestprice.generator'],
            dependencies: ['stpi_gh_bestprice.viewer'],
        },
        licenses: {
            privileges: ['stpi_gh_bestprice.licenses'],
            dependencies: ['stpi_gh_bestprice.viewer'],
        },
        pricing: {
            privileges: ['stpi_gh_bestprice.pricing'],
            dependencies: ['stpi_gh_bestprice.viewer'],
        },
        analytics: {
            privileges: ['stpi_gh_bestprice.analytics'],
            dependencies: ['stpi_gh_bestprice.viewer'],
        },
        purchaser: {
            privileges: ['stpi_gh_bestprice.purchaser'],
            dependencies: ['stpi_gh_bestprice.licenses'],
        },
    },
});
Component.register('stpi-gh-bestprice-index', {
    template,
    inject: ['stpiGhBestpriceApi', 'acl'],
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            tab: 'overview',
            loading: true,
            busy: false,
            state: {},
            feeds: [],
            total: 0,
            page: 1,
            feed: empty(),
            selectedId: null,
            errors: [],
            errorTotal: 0,
            errorPage: 1,
            plans: null,
            result: null,
            pricing: {
                netCost: '',
                netOverhead: '',
                marginPercent: '',
                vatPercent: '',
            },
            profit: {
                netRevenue: '',
                purchaseCost: '',
                shippingCost: '',
                paymentCost: '',
                bestpriceCost: '',
                advertisingCost: '',
                returnCost: '',
                otherCost: '',
            },
        };
    },
    computed: {
        tabs() {
            return [
                'overview',
                'feeds',
                'mapping',
                'quality',
                'pricing',
                'analytics',
                'plans',
                'help',
            ].map((name) => ({
                name,
                label: this.$tc(`stpiGhBestprice.tab.${name}`),
            }));
        },
        domainOptions() {
            return (this.state.domains || [])
                .filter((d) => d.salesChannelId === this.feed.salesChannelId)
                .map((d) => ({ value: d.id, label: d.url }));
        },
        channelOptions() {
            return (this.state.salesChannels || []).map((c) => ({
                value: c.id,
                label: c.name,
            }));
        },
        countryOptions() {
            return (this.state.countries || []).map((c) => ({
                value: c.id,
                label: c.name,
            }));
        },
        columns() {
            return ['name', 'enabled'].map((property) => ({
                property,
                label: this.$tc(`stpiGhBestprice.field.${property}`),
                primary: property === 'name',
            }));
        },
        errorColumns() {
            return ['productId', 'field', 'severity', 'code'].map(
                (property) => ({
                    property,
                    label: this.$tc(`stpiGhBestprice.field.${property}`),
                })
            );
        },
        selectionFields() {
            return [
                { key: 'productIds', entity: 'product' },
                { key: 'categoryIds', entity: 'category' },
                { key: 'manufacturerIds', entity: 'product_manufacturer' },
                { key: 'tagIds', entity: 'tag' },
                { key: 'excludedProductIds', entity: 'product' },
            ];
        },
        mappingFields() {
            return [
                'title',
                'brand',
                'MPN',
                'barcode',
                'description',
                'color',
                'size',
            ];
        },
        publicUrl() {
            if (!this.feed.id || !this.feed.token) return '';
            const d = (this.state.domains || []).find(
                (d) => d.id === this.feed.domainId
            );
            return `${d?.url || ''}/bestprice-feed/${this.feed.id}/${this.feed.token}.xml`;
        },
        canSave() {
            return (
                this.acl.can('stpi_gh_bestprice.editor') &&
                !this.busy &&
                this.feed.name.trim() &&
                this.feed.salesChannelId &&
                this.feed.domainId &&
                this.feed.countryId &&
                this.feed.shippingFallbackConfirmed
            );
        },
    },
    created() {
        this.load();
    },
    methods: {
        async request(method, path, data) {
            return this.stpiGhBestpriceApi.request(method, path, data);
        },
        fail(error) {
            this.createNotificationError({
                title: this.$tc('stpiGhBestprice.title'),
                message: this.$tc(
                    error?.response?.status === 403
                        ? 'stpiGhBestprice.error.denied'
                        : 'stpiGhBestprice.error.request'
                ),
            });
        },
        async load() {
            this.loading = true;
            try {
                this.state = await this.request('get', '/state');
                await this.loadFeeds();
                if (this.acl.can('stpi_gh_bestprice.licenses'))
                    this.plans = await this.request('get', '/entitlements');
            } catch (error) {
                this.fail(error);
            } finally {
                this.loading = false;
            }
        },
        async loadFeeds() {
            const data = await this.request(
                'get',
                `/feeds?page=${this.page}&limit=25`
            );
            this.feeds = data.items;
            this.total = data.total;
        },
        async paginate({ page }) {
            this.page = page;
            try {
                await this.loadFeeds();
            } catch (e) {
                this.fail(e);
            }
        },
        newFeed() {
            this.selectedId = null;
            this.feed = empty();
            this.tab = 'feeds';
            this.result = null;
        },
        async edit(item) {
            this.busy = true;
            try {
                this.feed = {
                    ...empty(),
                    ...(await this.request('get', `/feeds/${item.id}`)),
                };
                this.selectedId = item.id;
                this.tab = 'feeds';
            } catch (e) {
                this.fail(e);
            } finally {
                this.busy = false;
            }
        },
        async save() {
            if (!this.canSave) return;
            this.busy = true;
            try {
                this.feed = await this.request(
                    this.selectedId ? 'put' : 'post',
                    `/feeds${this.selectedId ? `/${this.selectedId}` : ''}`,
                    this.feed
                );
                this.selectedId = this.feed.id;
                await this.loadFeeds();
                this.createNotificationSuccess({
                    message: this.$tc('stpiGhBestprice.saved'),
                });
            } catch (e) {
                this.fail(e);
            } finally {
                this.busy = false;
            }
        },
        async build(item) {
            this.busy = true;
            try {
                await this.request('post', `/feeds/${item.id}/build`);
                this.createNotificationSuccess({
                    message: this.$tc('stpiGhBestprice.queued'),
                });
                await this.loadFeeds();
            } catch (e) {
                this.fail(e);
            } finally {
                this.busy = false;
            }
        },
        async quality(item) {
            this.selectedId = item.id;
            this.tab = 'quality';
            this.errorPage = 1;
            await this.loadErrors();
        },
        async loadErrors() {
            if (!this.selectedId) return;
            try {
                const data = await this.request(
                    'get',
                    `/feeds/${this.selectedId}/errors?page=${this.errorPage}`
                );
                this.errors = data.items;
                this.errorTotal = data.total;
            } catch (e) {
                this.fail(e);
            }
        },
        async errorPaginate({ page }) {
            this.errorPage = page;
            await this.loadErrors();
        },
        async preview(kind) {
            this.busy = true;
            this.result = null;
            try {
                this.result = await this.request(
                    'post',
                    kind === 'pricing'
                        ? '/pricing/preview'
                        : '/analytics/profitability',
                    kind === 'pricing' ? this.pricing : this.profit
                );
            } catch (e) {
                this.fail(e);
            } finally {
                this.busy = false;
            }
        },
        async buy(identifier) {
            if (
                !this.acl.can('stpi_gh_bestprice.purchaser') ||
                !this.plans?.purchasable?.includes(identifier) ||
                Shopware.InAppPurchase.isActive(
                    'StPiGhBestPriceIntegration',
                    identifier
                )
            )
                return;
            Shopware.Store.get('inAppPurchaseCheckout').request(
                { identifier },
                'StPiGhBestPriceIntegration'
            );
        },
        formatResult(key, value) {
            if (typeof value === 'boolean')
                return this.$tc(
                    value ? 'stpiGhBestprice.yes' : 'stpiGhBestprice.no'
                );
            if (key === 'source' && value === 'merchant-input')
                return this.$tc('stpiGhBestprice.merchantInput');
            if (Array.isArray(value))
                return value.length
                    ? value
                          .map((field) =>
                              this.$tc(`stpiGhBestprice.field.${field}`)
                          )
                          .join(', ')
                    : this.$tc('stpiGhBestprice.none');
            return value === null
                ? this.$tc('stpiGhBestprice.missing')
                : typeof value === 'object'
                  ? JSON.stringify(value)
                  : String(value);
        },
    },
});
Module.register('stpi-gh-bestprice', {
    type: 'plugin',
    name: 'stpi-gh-bestprice',
    title: 'stpiGhBestprice.title',
    description: 'stpiGhBestprice.subtitle',
    color: '#16758a',
    icon: 'regular-shopping-bag',
    routes: {
        index: {
            component: 'stpi-gh-bestprice-index',
            path: 'index',
            meta: { privilege: 'stpi_gh_bestprice.viewer' },
        },
    },
    navigation: [
        {
            id: 'stpi-gh-bestprice',
            label: 'stpiGhBestprice.title',
            color: '#16758a',
            path: 'stpi.bestprice.index',
            parent: 'sw-marketing',
            position: 80,
            privilege: 'stpi_gh_bestprice.viewer',
        },
    ],
});
