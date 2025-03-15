<template>
    <component
        v-show="typeof store.content[uuid].visible == 'undefined' ? true : store.content[uuid].visible"
        :style="[styles]"
        :class="[classes]"
        :is="computedTag"
        :ref="store.content[uuid].slug"
        :role="store.content[uuid].role"
        :maxlength="store.content[uuid].maxlengthField ? data[store.content[uuid].maxlengthField] : store.content[uuid].maxlength"
        :max="store.content[uuid].maxField ? data[store.content[uuid].maxField] : store.content[uuid].max"
        :pattern="store.content[uuid].patternField ? data[store.content[uuid].patternField] : store.content[uuid].pattern"
        :minlength="store.content[uuid].minlengthField ? data[store.content[uuid].minlengthField] : store.content[uuid].minlength"
        :min="store.content[uuid].minField ? data[store.content[uuid].minField] : store.content[uuid].min"
        :rows="store.content[uuid].rowsField ? data[store.content[uuid].rowsField] : store.content[uuid].rows"
        :size="store.content[uuid].sizeField ? data[store.content[uuid].sizeField] : store.content[uuid].size"
        :accept="store.content[uuid].autofocusField ? data[store.content[uuid].autofocusField] : store.content[uuid].list"
        :autofocus="store.content[uuid].autofocusField ? data[store.content[uuid].autofocusField] : store.content[uuid].autofocus"
        :list="store.content[uuid].listField ? data[store.content[uuid].listField] : store.content[uuid].list"
        :disabled="store.content[uuid].disabledField ? data[store.content[uuid].disabledField] : store.content[uuid].disabled"
        :step="store.content[uuid].stepField ? data[store.content[uuid].stepField] : store.content[uuid].step"
        :required="store.content[uuid].requiredField ? data[store.content[uuid].requiredField] : store.content[uuid].required"
        :placeholder="store.content[uuid].placeholderField ? data[store.content[uuid].placeholderField] : store.content[uuid].placeholder"
        :autocomplete="store.content[uuid].autocompleteField ? data[store.content[uuid].autocompleteField] : store.content[uuid].autocomplete"
        :type="computedType"
        :href="store.content[uuid].hrefField ? data[store.content[uuid].hrefField] : store.content[uuid].href"
        :name="store.content[uuid].nameField ? data[store.content[uuid].nameField] : store.content[uuid].inputName" 
        :value="computedValue" 
        :id="store.content[uuid].idField ? data[store.content[uuid].idField] : store.content[uuid].id"
        :checked="store.content[uuid].checkedField ? data[store.content[uuid].checkedField] : store.content[uuid].checked"
        @input="onInputHandler"
        @change.stop="onChangeHandler"
        @keyup="onKeyUpHandler"
        @blur="onBlurHandler"
        @focus="onFocusHandler" 
        @dblclick.stop="onDblClickHandler"
        @click.stop="onClickHandler"
        @mouseover="onMouseOverHandler"
        @mouseleave="onMouseLeaveHandler">{{ computedInterpolation }}<component
            v-for="(item, index) in store.content[uuid].data" 
            :key="index"
            :data="data"
            :is="store.content[item].type"
            :uuid="item"
            :enabled="store.content[uuid].enabled">
        </component>
    </component>
</template>

<script>
    import debounce from 'lodash/debounce';
    import { Container, Validator } from "stellifyjs";
    import { StellifyStore } from '../../stores/StellifyStore';
    export default {
        data() {
            return {
                trigger: '',
                store: StellifyStore()
            }
        },
        props: {
            parent: {
                type: String,
                default: null
            },
            uuid: {
                type: String,
                default: null
            },
            data: {
                type: String,
                default: {}
            }
        },
        computed: {
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
            computedValue() {
                if (this.store.content[this.uuid].valueField) {
                    return this.data[this.store.content[this.uuid].valueField];
                }
                if (this.store.content[this.uuid].value) {
                    return this.store.content[this.uuid].value;
                }
                return '';
            },
            computedTag() {
                return this.store.content[this.uuid].tagField ? this.data[this.store.content[this.uuid].tagField] : this.store.content[this.uuid].tag;
            },
            computedType() {
                if (this.computedTag == 'select') {
                    return false;
                }
                return this.store.content[this.uuid].inputTypeField ? this.data[this.store.content[this.uuid].inputTypeField] : this.store.content[this.uuid].inputType
            },
            classes() {
                if (typeof this.store.content[this.uuid].enabled != 'undefined') {
                    if (this.store.content[this.uuid].enabled) {
                        return [this.store.content[this.uuid].classes, this.store.content[this.uuid].enabledClasses];
                    } else {
                        return [this.store.content[this.uuid].classes, this.store.content[this.uuid].disabledClasses];
                    }
                }
                return this.store.content[this.uuid].classes;
            },
            styles() {
                return {
                    fontFamily: typeof this.store.content[this.uuid].fontFamily != 'undefined' ? this.store.content[this.uuid].fontFamily : null
                };
            }
        },
        methods: {
            onClickHandler(event) {
                this.store.variables.lastClicked = this.store.content[this.uuid].slug;
                if (this.store.content[this.uuid].onClick) {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid].onClick);
                }
            },
            onDblClickHandler(event) {
                if (this.store.content[this.uuid].onDblClick) {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid].onDblClick);
                }
            },
            onKeyUpHandler(event) {
                if (typeof this.store.content[this.uuid]['onKeyUp'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    if (event.keyCode == this.store.content[this.uuid].keyCode) {
                        this.store.runMethod(this.store.content[this.uuid]['onKeyUp']);
                    }
                }
            },
            onBlurHandler(event) {
                if (typeof this.store.content[this.uuid]['onBlur'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onBlur']);
                }
            },
            onInputHandler(event) {
                if (typeof this.store.content[this.uuid]['onInput'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onInput']);
                }
            },
            onChangeHandler(event) {
                if (typeof this.store.content[this.uuid]['onChange'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onChange']);
                }
            },
            onFocusHandler(event) {
                if (typeof this.store.content[this.uuid]['onFocus'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onFocus']);
                }
            },
            onMouseOverHandler(event) {
                if (typeof this.store.content[this.uuid]['onMouseOver'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onMouseOver']);
                }
            },
            onMouseLeaveHandler(event) {
                if (typeof this.store.content[this.uuid]['onMouseLeave'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onMouseLeave']);
                }
            }
        },
    }
</script>
