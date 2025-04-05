<script>
import {Dropzone} from "dropzone";
import LoaderComponent from "@/Components/GalleryAdmin/LoaderComponent.vue";
import ImageGalleryEditComponent from "@/Components/GalleryAdmin/ImageGalleryEditComponent.vue";
import Draggable from "vuedraggable";
import {ref} from 'vue'

export default {
    name: "GalleryAdmin",
    components: {LoaderComponent, ImageGalleryEditComponent, Draggable, ref},
    data() {
        return {
            dropzone: null,
            gallery: {},
            fileProgress: 0,
            fileCurrent: '',
            vis: false,
            glrContent: 'block',
            drag: false,
            cards: null
        }
    },
    props: {
        glr: Number,
        ttl: String
    },
    mounted() {
        this.$el.closest('form')?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault(); // Отключаем submit формы при нажатии Enter
            }
        });
        this.dropzone = new Dropzone(this.$refs.dropzone, {
            url: "/api/store",
            autoProcessQueue: false,
            addRemoveLinks: true,
        })
        this.getGallery()

    },
    methods: {
        store() {
            const data = new FormData()
            const files = this.dropzone.getAcceptedFiles()
            if (files.length > 0) {
                files.forEach(file => {
                    data.append('images[]', file)
                    this.dropzone.removeFile(file)
                })
                data.append('gallery_id', this.glr)
                axios.post('/api/store', data)
                    .then(res => {
                            this.getGallery()
                        },
                    )
            } else confirm('выберите изображения')
        },
        getGallery() {
            this.vis = true
            this.glrContent = 'none'
            axios.get('/api/gallery/' + this.glr)
                .then(res => {
                        this.gallery = res.data.data
                        this.vis = false
                        this.glrContent = 'block'
                        this.cards = this.gallery.images
                    },
                )
        },
        onChange(e) {
            let item = e.added || e.moved
            if (!item) return
            let index = item.newIndex
            console.log(this.cards[index])
            let prevCard = this.cards[index - 1]
            let nextCard = this.cards[index + 1]
            let card = this.cards[index]
            let position = card.position
            let image_id = card.id

            if (prevCard && nextCard) {
                position = (prevCard.position + nextCard.position) / 2
            } else if (prevCard) {
                position = prevCard.position + (prevCard.position) / 2
            } else if (nextCard) {
                position = nextCard / 2
            }
            const dt = new FormData()
            dt.append('position', position)

            axios.post('/api/gallery/' + image_id + '/move', dt)
                .then(res => {
                        //this.getGallery()
                    },
                )

        }
    }
}
</script>

<template>
    <div class="flex flex-row">
        <div class="w-4/12">
            <div class="font-bold p-5 text-gray-600 text-center">
                Загрузка изображений в галерею
            </div>
            <div ref="dropzone"
                 class="py-20 m-5 bg-gray-600 rounded-3xl text-white text-center cursor-pointer border border-b-cyan-900 hover:bg-cyan-950">
                Кликните для загрузки изображений<br/>или перетащите файлы в это поле
            </div>
            <div class="text-center">
                <input @click.prevent="store" @keyup.enter.prevent type="button"
                       class="bg-cyan-700 text-white p-5 rounded-md cursor-pointer"
                       value="Загрузить фото">
            </div>

        </div>
        <div class="w-8/12">
            <loader-component :vis="vis"/>


            <Draggable
                v-model="cards"
                group="cards"
                item-key="id"
                class="w-100"
                drag-class="drag"
                drop-class="drop"
                @change="onChange"
            >
                <template #item="{element}">
                    <div
                        :card="element"
                        :style="{ display: glrContent }"
                    >
                        <image-gallery-edit-component
                            :image=element
                            :i=element.id
                            :showPic=true
                        />
                    </div>
                </template>
            </Draggable>

            <!--            <div-->
            <!--                class="w-100"-->
            <!--                v-for="(image, i) in this.gallery.images"-->
            <!--                :key="image.id + '-' + i"-->
            <!--                :style="{ display: glrContent }"-->
            <!--            >-->
            <!--                <image-gallery-edit-component-->
            <!--                    :image=image-->
            <!--                    :i=i-->
            <!--                    :showPic=true-->
            <!--                />-->
            <!--            </div>-->

        </div>
    </div>

</template>

<style scoped>
.drag > div {
    transform: rotate(-5deg);
}

.drop {
    background: lightgray;
}

.drop > div {
    visibility: hidden;
}
</style>
