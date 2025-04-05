<script>
import IconTrash from "@/Components/GalleryAdmin/Icons/IconTrash.vue";
import {nextTick} from "vue";

export default {
    components: {IconTrash},
    data() {
        return {
            showPreview: true,
            showEdit: false,
            localimgname: [],
            pic: true,
            newTitle: "",
        }
    },
    props: {
        image: Object,
        i: Number,
        showPic: Boolean
    },
    mounted() {
        this.pic = this.showPic
    },
    methods: {
        async fileDelete() {
            let form = new FormData();
            if (confirm('Вы уверенны, что удаляете изображение?')) {
                await axios.post('/api/gallery/' + this.image.id + '/delete')
                    .then(response => {
                        this.pic = false
                    })
            }
        },
        picTrue() {
            this.pic = true
        },
        openform() {
            this.newTitle = this.image.title; // Устанавливаем актуальное значение перед открытием input

            this.showPreview = false
            this.showEdit = true

            nextTick(() => {
                let input = document.getElementById('ren_' + this.image.id);
                if (input) input.focus();
            });

            // let i = 'ren_' + this.image.id;
            // document.getElementById(i).focus();
        },
        submitRename() {
            event?.preventDefault(); // Останавливаем submit формы

            if (this.newTitle.trim() === "" || this.newTitle === this.image.title) {
                this.showPreview = true;
                this.showEdit = false;
                return;
            }

            let data = new FormData();
            data.append('title', this.newTitle);

            axios.post('/api/gallery/' + this.image.id + '/rename-title', data)
                .then(() => {
                    this.image.title = this.newTitle; // Обновляем название
                    this.showPreview = true;
                    this.showEdit = false;

                    this.$nextTick(() => {
                        let input = document.getElementById('ren_' + this.image.id);
                        if (input) input.blur(); // Принудительно убираем фокус после сохранения
                    });
                });
        },
        escRename() {
            this.showPreview = true
            this.showEdit = false
        }
    }
}
</script>

<template>

    <div class="glr_item">
        <div class="" v-show="!pic">
            ИЗОБРАЖЕНИЕ УДАЛЕНО
        </div>

        <div class="flex flex-row" v-show="pic">
            <div class="" style="width: 600px;">
                <a :href="image.image" target="_blank">
                    <img :src="image.thmb" alt="">
                </a>

            </div>
            <div class="p-5 w-full">
                <div class="flex flex-col">
                    <div class="">
                        <p class="text-xl">ID: {{ i }}</p>
                        <p class="font-bold text-red-700 p-2 border border-gray-300 w-full cursor-pointer"
                           style="height: 42px;"
                           @click.prevent="openform" v-show="showPreview"> {{
                                image.title
                            }}</p>
                        <p class="font-bold text-gray-600 l"><input class="w-full"
                                                                    v-model="newTitle"
                                                                    :id="'ren_' + image.id"
                                                                    @keyup.enter.prevent.stop="submitRename"
                                                                    @keyup.esc="escRename"
                                                                    @blur="submitRename"
                                                                    v-show="!showPreview"/>
                        </p>
                    </div>
                    <div class=" p-5 text-2xl w-full text-right flex justify-end">
                        <IconTrash v-if="pic" @fileDelete="fileDelete"/>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
.glr_item {
    margin: 5px;
    border: 1px solid #ccc;
    padding: 5px;
    background-color: #e8e8e8;
    border-radius: 5px;
}
</style>
