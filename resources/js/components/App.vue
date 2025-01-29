<template>
    <component
        v-for="(uuid, index) in store.page.data" 
        :key="index + uuid"
        :is="store.content[uuid].type"
        :data="''"
        :uuid="uuid">
    </component>
</template>

<script>
    import { StellifyStore } from '../stores/StellifyStore';
    export default {
        data() {
            return {
                store: StellifyStore()
            }
        }
    }
</script>
