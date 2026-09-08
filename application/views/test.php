<!-- <style>
    .circle {
        margin: 0 auto;
        width: 32px;
        height: 32px;
        border: 3px solid transparent;
        border-top-color: black;
        /* border: 1px black solid; */
        border-radius: 50%;
        animation: rotate 1s 0.8s infinite, hue2 1s infinite alternate;
    }

    @keyframes rotate {
        from {rotate: 0deg;}
        to {rotate: 360deg;}
    }

    @keyframes hue2 {
        0% {border-top-color: crimson;}
        25% {border-top-color: red;}
        50% {border-top-color: yellow;}
        100% {border-top-color: orange;}
    }

    .car {
        height: 64px;
        width: 216px;
        background-color: blue;
        position: relative;
    }

    .wheel {
        width: 50px;
        aspect-ratio: 1;
        color: #854f1d;
        border-radius: 50%;
        display: grid;
        background: 
            conic-gradient(from 90deg at 4px 4px,#0000 90deg,currentColor 0)
            -4px -4px/calc(50% + 2px) calc(50% + 2px),
            radial-gradient(farthest-side,currentColor 6px,#0000 7px calc(100% - 6px),currentColor calc(100% - 5px)) no-repeat;
        animation: l10 2s infinite linear;
        position: absolute;
        top: 38px;
        left: 20px;
    }

    .wheel2 {
        left: 138px;
    }

    .wheel:before {
        content: "";
        border-radius: inherit;
        background: inherit;
        transform: rotate(45deg);
    }

    @keyframes l10 {to{transform: rotate(.5turn)}}

</style>
<div class="circle"></div>

<div class="car">
    <div class="wheel"></div>
    <div class="wheel wheel2"></div>
</div> -->

<form method="post" action="<?= base_url('Test/test') ?>">
    <input type="text" name="name">
    <button type="submit">submit</button>
</form>