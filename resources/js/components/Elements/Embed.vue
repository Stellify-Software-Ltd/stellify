<template>
    <component
        :style="[styles]"
        :class="classes"
        :is="computedTag"
        :src="store.content[uuid].src" 
        :width="store.content[uuid].width" 
        :height="store.content[uuid].height"
        :frameborder="store.content[uuid].frameborder" 
        :allowtransparency="store.content[uuid].allowtransparency"
        :allowpaymentrequest="store.content[uuid].allowpaymentrequest" 
        :referrerpolicy="store.content[uuid].referrerpolicy"
        :allow="store.content[uuid].allow ? store.content[uuid].allow : 'encrypted-media'">
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
            }
        },
        computed: {
            computedTag() {
                return store.content[this.uuid].tag ? store.content[this.uuid].tag : 'div'
            },
            classes() {
                return store.content[this.uuid].classes;
            },
            styles() {
                return {
                    minHeight: store.content[this.uuid].minHeight + (store.content[this.uuid].minHeightUnit ? store.content[this.uuid].minHeightUnit : 'px'),
                    minWidth: store.content[this.uuid].minWidth + (store.content[this.uuid].minWidthUnit ? store.content[this.uuid].minWidthUnit : 'px'),
                    height: store.content[this.uuid].height + (store.content[this.uuid].heightUnit ? store.content[this.uuid].heightUnit : 'px'),
                    width: store.content[this.uuid].width + (store.content[this.uuid].widthUnit ? store.content[this.uuid].widthUnit : 'px')
                }
            }
        }
    }
</script>
