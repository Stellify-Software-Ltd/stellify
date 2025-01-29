<template>
    <template v-if="store.content[uuid].variable && typeof store.variables[store.content[uuid].variable] == 'object'" v-for="(item, index) in store.variables[store.content[uuid].variable]">
        <component
            v-show="visible"
            @click.stop="onClickHandler"
            @dblclick.stop="onDblClickHandler"
            :is="computedTag"
            :href="store.content[uuid].hrefField ? data[store.content[uuid].hrefField] : store.content[uuid].href"
            :target="store.content[uuid].target"
            :title="store.content[uuid].title"
            :download="store.content[uuid].download"
            :rel="store.content[uuid].rel"
            :ref="store.content[uuid].ref"
            :src="store.content[uuid].srcField ? data[store.content[uuid].srcField] : store.content[uuid].src"
            :ariaLabel="store.content[uuid].ariaLabel"
            :role="store.content[uuid].role"
            :class="[classes]">
                <component
                    v-for="(uuid, index) in store.content[uuid].data" 
                    :key="index"
                    :data="item"
                    :is="store.content[uuid].type"
                    :uuid="uuid">
                </component>
        </component>
    </template>
    <template v-else-if="store.content[uuid].dataVariable && typeof store.variables[data[store.content[uuid].dataVariable]] == 'object'" v-for="(item, index) in store.variables[data[store.content[uuid].dataVariable]]">
        <component
            v-show="visible"
            @click.stop="onClickHandler"
            @dblclick.stop="onDblClickHandler"
            :is="computedTag"
            :href="store.content[uuid].hrefField ? data[store.content[uuid].hrefField] : store.content[uuid].href"
            :target="store.content[uuid].target"
            :title="store.content[uuid].title"
            :download="store.content[uuid].download"
            :rel="store.content[uuid].rel"
            :ref="store.content[uuid].ref"
            :src="store.content[uuid].srcField ? data[store.content[uuid].srcField] : store.content[uuid].src"
            :ariaLabel="store.content[uuid].ariaLabel"
            :role="store.content[uuid].role"
            :class="[classes]">
                <component
                    v-for="(uuid, index) in store.content[uuid].data" 
                    :key="index"
                    :data="item"
                    :is="store.content[uuid].type"
                    :uuid="uuid">
                </component>
        </component>
    </template>
    <component
            v-else-if="store.content[uuid].variable && typeof store.variables[store.content[uuid].variable] != 'undefined'" v-for="(item, index) in store.variables[store.content[uuid].variable]"
            v-show="visible"
            :is="computedTag"
            :href="store.content[uuid].hrefField ? data[store.content[uuid].hrefField] : store.content[uuid].href"
            :target="store.content[uuid].target"
            :title="store.content[uuid].title"
            :download="store.content[uuid].download"
            :rel="store.content[uuid].rel"
            :ref="store.content[uuid].ref"
            :src="store.content[uuid].srcField ? data[store.content[uuid].srcField] : store.content[uuid].src"
            :ariaLabel="store.content[uuid].ariaLabel"
            :role="store.content[uuid].role"
            :class="[classes]">
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
                default: ''
            }
        },
        computed: {
            visible() {
                return typeof this.store.content[this.uuid].visible == 'undefined' ? true : this.store.content[this.uuid].visible;
            },
            computedTag() {
                return this.store.content[this.uuid].tag ? this.store.content[this.uuid].tag : 'div'
            },
            classes() {
                if (this.store.content[this.uuid].enabled) {
                    return this.store.content[this.uuid].enabledClasses;
                }
                return this.store.content[this.uuid].classes;
            }
        },
        methods: {
            onClickHandler() {
                this.store.variables.lastClicked = this.store.content[this.uuid].slug;
            },
            onDblClickHandler() {
                this.store.variables.lastClicked = this.store.content[this.uuid].slug;
            }
        }
    }
</script>