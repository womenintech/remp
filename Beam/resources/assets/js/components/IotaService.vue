<script type="text/javascript">
    import Axios from "axios"
    import EventHub from "./EventHub"

    export default {
        name: 'iota-service',
        props: {
            articleIds: {
                type: Array,
                required: true,
            },
            pageviewMinuteRanges: {
                type: Array,
                default: function() {
                    return [
                        {minutes: 15, label: "15m", order: 0},
                        {minutes: undefined, label: "Total", order: 1},
                    ];
                },
            },
            variantsMinuteRanges: {
                type: Array,
                default: function() {
                    return [
                        {minutes: 15, label: "15m", order: 0},
                        {minutes: undefined, label: "Total", order: 0},
                    ];
                },
            },
            baseUrl: {
                type: String,
                required: true,
            },
            configUrl: {
                type: String,
                required: false
            },
            httpHeaders: {
                type: Object,
                default: function() {
                    return {};
                },
            },
        },
        data: function() {
            return {
                config: null
            }
        },
        created: function() {
            let vm = this;
            Object.keys(this.httpHeaders).forEach(function (name) {
                Axios.defaults.headers.common[name] = vm.httpHeaders[name];
            });
            Axios.defaults.headers.common['Content-Type'] = 'application/json';

            new Promise((resolve, reject) => {
                if (this.configUrl) {
                    Axios.get(this.configUrl).then(({ data }) => resolve(data), reject)
                } else {
                    resolve()
                }
            }).then((config) => {
                if (config) {
                    EventHub.$emit('config-changed', config)
                }

                let now = new Date();

                this.fetchCommerceStats();
                this.fetchPageviewStats(now);
                this.fetchVariantStats(now, ["title_variant", "image_variant"]);
            })
        },
        methods: {
            fetchCommerceStats: function() {
                const payload = {
                    "filter_by": [
                        {
                            "tag": "article_id",
                            "values": this.articleIds,
                        },
                    ],
                    "group_by": [
                        "article_id",
                    ],
                };

                Axios.post(this.baseUrl + '/journal/commerce/steps/purchase/count', payload)
                    .then(function (response) {
                        let counts = {}
                        for (const group of response.data) {
                            counts[group["tags"]["article_id"]] = group["count"]
                        }
                        EventHub.$emit('content-conversions-counts-changed', counts)
                    })
                    .catch(function (error) {
                        console.warn(error);
                    });
            },

            fetchPageviewStats: function(now) {
                for (let range of this.pageviewMinuteRanges) {
                    const payload = {
                        "filter_by": [
                            {
                                "tag": "article_id",
                                "values": this.articleIds,
                            },
                        ],
                        "group_by": [
                            "article_id",
                        ],
                    };

                    if (range.minutes !== undefined) {
                        let d = new Date(now.getTime());
                        d.setMinutes(d.getMinutes() - range.minutes);
                        payload["time_after"] = d.toISOString()
                    }

                    Axios.post(this.baseUrl + '/journal/pageviews/actions/load/unique/browsers', payload)
                        .then(function (response) {
                            let counts = {}
                            for (const group of response.data) {
                                counts[group["tags"]["article_id"]] = group["count"]
                            }
                            EventHub.$emit('content-pageviews-changed', range, counts)
                        })
                        .catch(function (error) {
                            console.warn(error);
                        });
                }
            },

            fetchVariantStats: function(now, variantTypes) {
                for (let range of this.variantsMinuteRanges) {

                    const variantPayload = {
                        "filter_by": [
                            {
                                "tag": "article_id",
                                "values": this.articleIds,
                            },
                        ],
                        "group_by": [
                            "article_id",
                        ].concat(variantTypes).concat(["social"]),
                    };

                    if (range.minutes !== undefined) {
                        let d = new Date(now.getTime());
                        d.setMinutes(d.getMinutes() - range.minutes);
                        variantPayload["time_after"] = d.toISOString()
                    }

                    Axios.post(this.baseUrl + '/journal/pageviews/actions/load/unique/browsers', variantPayload)
                        .then(function (response) {
                            let counts = {};
                            for (const group of response.data) {
                                if (group["tags"]["social"] !== "") {
                                    // social networks always get variant A due to the nature of replacing
                                    // we include only direct pageviews into the A/B test
                                    continue
                                }
                                if (parseInt(group["count"]) === 0) {
                                    continue;
                                }

                                for (const variantType of variantTypes) {
                                    const variant = group["tags"][variantType];
                                    if (variant === "") {
                                        continue;
                                    }
                                    const articleId = group["tags"]["article_id"];
                                    if (!counts[variantType]) {
                                        counts[variantType] = {};
                                    }
                                    if (!counts[variantType][articleId]) {
                                        counts[variantType][articleId] = {}
                                    }
                                    if (!counts[variantType][articleId][variant]) {
                                        counts[variantType][articleId][variant] = 0;
                                    }
                                    counts[variantType][articleId][variant] += group["count"]
                                }
                            }

                            EventHub.$emit('content-variants-changed', variantTypes, range, counts)
                        })
                        .catch(function (error) {
                            console.warn(error);
                        });
                }
            },
        },


    }
</script>
