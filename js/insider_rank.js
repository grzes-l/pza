$(function () {
    $("body").on("change", "span#rank_selector select", function (event, ui)
    {
        window.location.href = "/insider/rank?id=" + $(this).val();
    });
});