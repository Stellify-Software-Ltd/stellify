<template>
    <component
        :style="[styles]"
        :is="computedTag"
        :src="store.content[this.uuid].src"
        :controls="!store.content[this.uuid].disableControls"
        :playsinline="store.content[this.uuid].playsinline" 
        :autoplay="store.content[this.uuid].autoplay" 
        :preload="store.content[this.uuid].preload" 
        :muted="store.content[this.uuid].muted" 
        :loop="store.content[this.uuid].loop"
        :class="classes">
        <component
            v-for="(uuid, index) in store.content[this.uuid].data" 
            :key="index"
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
                store: ProductionStore(),
                animation: null
            }
        },
        props: {
            uuid: {
                type: String,
                default: null
            },
            data: {
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
        },
        mounted: function() {
            if (store.content[this.uuid].autoplay) {
                var videos = document.getElementsByClassName('autoplay');
                for (var i = 0; i < videos.length; i++) {
                    videos[i].muted = true;
                    videos[i].play();
                }
            }
        }
    }
</script>
