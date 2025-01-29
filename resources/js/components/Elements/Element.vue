<template>
    <component
        v-show="visible"
        :is="computedTag"
        @click.stop="onClickHandler"
        :height="store.content[uuid].height"
        :width="store.content[uuid].width"
        :href="computedHref"
        :target="store.content[uuid].target"
        :title="store.content[uuid].title"
        :download="store.content[uuid].download"
        :rel="store.content[uuid].rel"
        :id="store.content[uuid].id"
        :ref="store.content[uuid].ref"
        :src="store.content[uuid].src"
        :ariaLabel="store.content[uuid].ariaLabel"
        :role="store.content[uuid].role"
        :class="[classes]"
        v-bind="store.content[uuid].customAttributes">{{ computedInterpolation }}<component
                v-for="(uuid, index) in store.content[uuid].data" 
                :key="index"
                :data="data"
                :is="store.content[uuid].type"
                :uuid="uuid">
            </component>
    </component>
</template>

<script>
    import { ProductionStore } from '../../stores/ProductionStore';
    export default {
        data() {
            return {
                store: ProductionStore()
            }
        },
        props: {
            uuid: {
                type: String,
                default: null
            },
            data: {
                type: [Object, String],
                default: null
            },
            enabled: {
                type: Boolean,
                default: null
            }
        },
        computed: {
            customAttributes() {
                return this.store.content[this.uuid].customAttributes
            },
            computedInterpolation() {
                if (this.store.content[this.uuid].text) {
                    return this.store.content[this.uuid].text;
                } else if (this.store.content[this.uuid].variable) {
                    return this.store.variables[this.store.content[this.uuid].variable];
                } else if (typeof this.store.content[this.uuid].textField != 'undefined') {
                    if (typeof this.data[this.store.content[this.uuid].textField] != 'undefined') {
                        return this.data[this.store.content[this.uuid].textField];
                    } else if (this.store.variables[this.store.content[this.uuid].variable] && typeof this.store.variables[this.store.content[this.uuid].variable][this.store.content[this.uuid].textField] != 'undefined') {
                        return this.store.variables[this.store.content[this.uuid].variable][this.store.content[this.uuid].textField];
                    }
                }
                return '';
            },
            computedHref() {
                let href = this.store.content[this.uuid].href ? this.store.content[this.uuid].href : '';
                if (typeof this.store.content[this.uuid].hrefField != 'undefined') {
                    if (typeof this.data[this.store.content[this.uuid].hrefField] != 'undefined') {
                        return (href + this.data[this.store.content[this.uuid].hrefField]);
                    }
                } else {
                    if (href) {
                        return href;
                    } else {
                        return null;
                    }
                }
            },
            visible() {
                let expression = '';
                if (Object.keys(this.store.jsTokens).length && this.store.content[this.uuid].visibleMethod && this.store.methods[this.store.content[this.uuid].visibleMethod].data) {
                    this.store.methods[this.store.content[this.uuid].visibleMethod].data.forEach((method) => {
                        if (this.store.statements[method].data) {
                            this.store.statements[method].data.forEach((clause) => {
                                if (
                                    typeof this.store.jsTokens[this.store.clauses[clause].type] != 'undefined' &&
                                    ['string', 'number', 'method', 'float', 'integer', 'object', 'property'].includes(this.store.clauses[clause].type) == false
                                ) {
                                    expression += this.store.jsTokens[this.store.clauses[clause].type];
                                } else {
                                    if (this.store.clauses[clause].type == 'variable' && typeof this.store.variables[this.store.clauses[clause].name] != 'undefined') {
                                        expression += 'this.store.variables.' + this.store.clauses[clause].name;
                                    } else {
                                        expression += this.store.clauses[clause].name;
                                    }
                                }
                            });
                        }
                    });
                    return eval(expression);
                }
                return typeof this.store.content[this.uuid].visible == 'undefined' ? true : this.store.content[this.uuid].visible === true || this.store.content[this.uuid].visible == 'true';
            },
            computedTag() {
                return this.store.content[this.uuid].tag ? this.store.content[this.uuid].tag : 'div'
            },
            classes() {
                if (typeof this.enabled != 'undefined') {
                    if (this.enabled) {
                        return [this.store.content[this.uuid].classes, this.store.content[this.uuid].enabledClasses];
                    } else {
                        return [this.store.content[this.uuid].classes, this.store.content[this.uuid].disabledClasses];
                    }
                }
                return this.store.content[this.uuid].classes;
            }
        },
        methods: {
            onClickHandler() {
                this.store.variables.lastClicked = this.store.content[this.uuid].slug;
                console.log('onClickHandler', this.data.path)
                if (this.data.path) {
                    this.store.variables.currentParent = this.data.path;
                }
            }
        }
    }
</script>