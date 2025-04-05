<template>
    <div class="lds-cover" v-show="vis" name="fade">
        <div class="loader2"></div>
    </div>
</template>

<style scoped>
.lds-cover {
    width: 80px;
    margin: 150px auto;
}

/* HTML: <div class="loader"></div> */
.loader2 {
    width: 60px;
    aspect-ratio: 1;
    display: flex;
    color: #000;
    border: 4px solid;
    box-sizing: border-box;
    border-radius: 50%;
    background: radial-gradient(circle 5px, currentColor 95%, #0000),
    linear-gradient(currentColor 50%, #0000 0) 50%/4px 60% no-repeat;
    animation: l1 2s infinite linear;
}

.loader2:before {
    content: "";
    flex: 1;
    background: linear-gradient(currentColor 50%, #0000 0) 50%/4px 80% no-repeat;
    animation: inherit;
}

@keyframes l1 {
    100% {
        transform: rotate(1turn)
    }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity .5s ease;
}

.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

</style>
<script>
export default {
    name: "LoaderComponent",
    data() {
        return {}
    },
    props: {
        vis: {
            default: false
        }
    },
}
</script>
