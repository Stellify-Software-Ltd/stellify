<template>
    <component
        :is="computedTag"
        :style="[styles, clipPath, backgroundImage, backgroundSize, backgroundRepeat, backgroundColour, backgroundPosition]"
        :id="store.content[uuid].id"
        :ref="store.content[uuid].slug"
        :src="store.content[uuid].src"
        :class="[classes]"
        >{{ store.content[this.uuid].text }}
        {{ typeof this.data[store.content[this.uuid].field] != 'undefined' ? this.data[store.content[this.uuid].field] : '' }}
        {{ typeof store.entities[store.content[this.uuid].field] != 'undefined' ? store.entities[store.content[this.uuid].field] : '' }}
        {{ store.content[this.uuid].appendedText }} <component
                v-for="(uuid, index) in store.content[uuid].data" 
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
                    minHeight: (store[store.content[this.uuid].minHeightVariable] ? store[store.content[this.uuid].minHeightVariable] : store.content[this.uuid].minHeight) + (store.content[this.uuid].minHeightUnit ? store.content[this.uuid].minHeightUnit : 'px'),
                    minWidth: (store[store.content[this.uuid].minWidthVariable] ? store[store.content[this.uuid].minWidthVariable] : store.content[this.uuid].minWidth) + (store.content[this.uuid].minWidthUnit ? store.content[this.uuid].minWidthUnit : 'px'),
                    height: (store[store.content[this.uuid].heightVariable] ? store[store.content[this.uuid].heightVariable] : store.content[this.uuid].height) + (store.content[this.uuid].heightUnit ? store.content[this.uuid].heightUnit : 'px'),
                    width: (store[store.content[this.uuid].widthVariable] ? store[store.content[this.uuid].widthVariable] : store.content[this.uuid].width) + (store.content[this.uuid].widthUnit ? store.content[this.uuid].widthUnit : 'px'),
                    top: (store[store.content[this.uuid].topVariable] ? store[store.content[this.uuid].topVariable] : store.content[this.uuid].top) + (store.content[this.uuid].topUnit ? store.content[this.uuid].topUnit : 'px'),
                    bottom: (store[store.content[this.uuid].bottomVariable] ? store[store.content[this.uuid].bottomVariable] : store.content[this.uuid].bottom) + (store.content[this.uuid].bottomUnit ? store.content[this.uuid].bottomUnit : 'px'),
                    right: (store[store.content[this.uuid].rightVariable] ? store[store.content[this.uuid].rightVariable] : store.content[this.uuid].right) + (store.content[this.uuid].rightUnit ? store.content[this.uuid].rightUnit : 'px'),
                    left: (store[store.content[this.uuid].leftVariable] ? store[store.content[this.uuid].leftVariable] : store.content[this.uuid].left) + (store.content[this.uuid].leftUnit ? store.content[this.uuid].leftUnit : 'px'),
                    borderRadius: (store[store.content[this.uuid].borderRadiusVariable] ? store[store.content[this.uuid].borderRadiusVariable] : store.content[this.uuid].borderRadius) + (store.content[this.uuid].borderRadiusUnit ? store.content[this.uuid].borderRadiusUnit : 'px'),
                    opacity: (store[store.content[this.uuid].opacityVariable] ? store[store.content[this.uuid].opacityVariable] : store.content[this.uuid].opacity),
                    transform: store.content[this.uuid].transform && store.content[this.uuid].transformType ? store.content[this.uuid].transformType + '(' + store.content[this.uuid].transformState ? this.state[store.content[this.uuid].transformState] + (store.content[this.uuid].transformUnit ? store.content[this.uuid].transformUnit : 'px') : store.content[this.uuid].transform + (store.content[this.uuid].transformUnit ? store.content[this.uuid].transformUnit : 'deg') + ')' : false,
                    filter: store.content[this.uuid].filter && store.content[this.uuid].filterType ? store.content[this.uuid].filterType + '(' + store.content[this.uuid].filterState ? this.state[store.content[this.uuid].filterState] + (store.content[this.uuid].filterUnit ? store.content[this.uuid].filterUnit : 'px') : store.content[this.uuid].filter + (store.content[this.uuid].filterUnit ? store.content[this.uuid].filterUnit : 'deg') + ')' : false
                }
            },
            clipPath() {
                let clipPathString = '';
                if (store.content[this.uuid].clipSource && store.content[this.uuid].clipDefinition) {
                    clipPathString += store.content[this.uuid].clipSource;
                    clipPathString += '(';
                    clipPathString += store.content[this.uuid].clipDefinition;
                    clipPathString += ')';
                }
                let clipPath = {'clip-path': clipPathString};
                return clipPath;
                //clip-path:polygon(0 0,100% 0,100% 100%)
            },
            backgroundImage() {
                if (store.content[this.uuid].backgroundImage) {
                    return {backgroundImage: `url(${store.content[this.uuid].backgroundImage})`};
                }
            },
            backgroundSize() {
                let backgroundSize = '';
                if (store.content[this.uuid].backgroundSize1 && store.content[this.uuid].backgroundSize2) {
                    backgroundSize += store.content[this.uuid].backgroundSize1 + ' ' + store.content[this.uuid].backgroundSize2;
                    if (store.content[this.uuid].backgroundSize3 && store.content[this.uuid].backgroundSize4) {
                        backgroundSize += ', ' + store.content[this.uuid].backgroundSize3 + ' ' + store.content[this.uuid].backgroundSize4;
                    }
                } else if (store.content[this.uuid].backgroundSize) {
                     backgroundSize = store.content[this.uuid].backgroundSize;
                }
                return {backgroundSize: backgroundSize};
            },
            backgroundRepeat() {
                return {backgroundRepeat: store.content[this.uuid].backgroundRepeat};
            },
            backgroundColour() {
                return {backgroundColour:store.content[this.uuid].backgroundColour};
            },
            backgroundPosition() {
                let positions = '';
                if (store.content[this.uuid].backgroundGradient) {
                    if (Array.isArray(store.content[this.uuid].backgroundGradient)) {
                        store.content[this.uuid].backgroundGradient.forEach(function (gradient, idx) {
                            if (gradient.backgroundPosition1 && gradient.backgroundPosition2) {
                                positions += gradient.backgroundPosition1 + ' ' + gradient.backgroundPosition2;
                            }
                            if (idx !== store.content[this.uuid].backgroundGradient.length - 1) {
                                positions += ', ';
                            }
                        }.bind(this));
                    }
                } else {
                    positions = {backgroundPosition: store.content[this.uuid].backgroundPosition};
                }
                return positions;
            },
            backgroundGradient() {
                let styles = {};
                if (store.content[this.uuid].backgroundGradient) {
                    if (Array.isArray(store.content[this.uuid].backgroundGradient)) {
                        let gradients = '';
                        store.content[this.uuid].backgroundGradient.forEach(function (gradient, idx) {
                            if (gradient.repeating) {
                                gradients += 'repeating-'
                            }
                            if (gradient.type == 'radial') {
                                gradients += 'radial'
                            } else {
                                gradients += 'linear'
                            }
                            gradients += '-gradient(';
                            if (gradient.backgroundLinearGradientAngle) {
                                gradients += gradient.backgroundLinearGradientAngle.toString() + gradient.backgroundLinearGradientAngleMeasure + ',';
                            }
                            if (gradient.shape) {
                                gradients += gradient.shape + ' ';
                            }
                            if (gradient.backgroundRadialExtent) {
                                gradients += gradient.backgroundRadialExtent + ' ';
                            }
                            if (gradient.backgroundRadialPosition1 && gradient.backgroundRadialPosition2) {
                                gradients += ` at ${gradient.backgroundRadialPosition1} ${gradient.backgroundRadialPosition2}, `;
                            } else if (gradient.backgroundRadialExtent) {
                                gradients += ', ';
                            }
                            if (Array.isArray(gradient.colourStops) && gradient.colourStops.length > 1) {
                                gradient.colourStops.forEach(function (colourStop, colourStopIdx) {
                                    gradients += colourStop.colour +  (typeof colourStop.stop != 'undefined' ? ' ' + colourStop.stop : '');
                                    if (colourStopIdx !== gradient.colourStops.length - 1) {
                                        gradients += ', ';
                                    }
                                });
                            }
                            gradients += ')';
                            if (gradient.coordinates && gradient.coordinates.x && gradient.coordinates.y) {
                                gradients += ' ' + gradient.coordinates.x + ' ' + gradient.coordinates.y + ' / ';
                            }
                            if (gradient.coordinates && gradient.coordinates.width && gradient.coordinates.height) {
                                gradients += ' ' + gradient.coordinates.width + ' ' + gradient.coordinates.height;
                            }
                            if (idx !== store.content[this.uuid].backgroundGradient.length - 1){
                                gradients += ', ';
                            }
                        }.bind(this));
                        //console.log(gradients)
                        styles['background'] = gradients;
                    }
                } else if (store.content[this.uuid].src) {
                    styles['background-image'] = 'url(' + store.content[this.uuid].src + ')';
                    this.$forceUpdate();
                }
                return styles;
            }
        },
        methods: {
            animate() {
                if (typeof store.content[this.uuid].keyframes != 'undefined') {
                    this.$nextTick(() => {
                        this.animation = this.$refs[store.content[this.uuid].slug].animate(
                            store.content[this.uuid].keyframes,
                            store.content[this.uuid].timings
                        );
                    });
                }
            },
            play() {
                if (this.animation) {
                    this.animation.play();
                }
            },
            stop() {
                if (this.animation) {
                    this.animation.cancel();
                }
            },
            pause() {
                if (this.animation) {
                    this.animation.pause();
                }
            },
            reverse() {
                if (this.animation) {
                    this.animation.reverse();
                }
            }
        },
        beforeMount: function () {
            if (store.content[this.uuid].observe) {
                store.content[this.uuid].id = store.content[this.uuid].slug;
                store.content[this.uuid].classes.push('observe');
            }
        },
        mounted() {
            if (store.content[this.uuid].playOnLoad) {
                this.animate();
                this.play();
            }
        }
    }
</script>