<template>
    <component
        v-for="(uuid, index) in store.content[this.uuid].data"
        :key="index + uuid"
        :is="store.content[uuid].type"
        :data="''"
        :uuid="uuid">
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
        },
        mounted() {
            if (this.store.content[this.uuid].target) {
                const source = new EventSource('/stellify/stream/elements/' + this.store.content[uuid].target);
                source.onmessage = (event) => {
                    const element = JSON.parse(event.data);
                    this.processElement(element);
                };
            }
        },
        methods: {
            processElement(element) {
                const slug = element.slug;
                if (slug == this.store.content[this.uuid].target) {
                    this.store.content[slug] = element;
                    if (typeof this.store.content[this.uuid].data == 'undefined') {
                        this.store.content[this.uuid].data = [];
                    }
                    this.store.content[this.uuid].data = [slug]
                } else {
                    this.store.content[slug] = element;
                }
            }
        }
    }
</script>
  