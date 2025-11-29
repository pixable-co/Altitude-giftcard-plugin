jQuery(function ($) {
  let selectedValue = null;
  let selectedPrice = null;

  /* TAB SWITCHING */
  $(".pxgc-tab-btn").on("click", function () {
    if ($(this).hasClass("active")) return;

    $(".pxgc-tab-btn").removeClass("active");
    $(this).addClass("active");

    const target = $(this).data("tab");
    const newPanel = $("#pxgc-tab-" + target);
    const oldPanel = $(".pxgc-tab-content.active");

    selectedValue = null;
    selectedPrice = null;
    $("#pxgc_price").text("");
    $(".pxgc_select").val("");

    oldPanel.fadeOut(200, function () {
      oldPanel.removeClass("active");
      newPanel.fadeIn(200).addClass("active");
    });
  });

  /* SELECT CHANGE */
  $(".pxgc_select").on("change", function () {
    const selected = $("option:selected", this);
    selectedValue = selected.val();
    selectedPrice = selected.data("price");

    if (selectedPrice) {
      $("#pxgc_price").text("£" + parseFloat(selectedPrice).toFixed(2));
    } else {
      $("#pxgc_price").text("£0.00");
    }
  });

  // ADD TO CART BUTTON
  $(".pxgc_add_button").on("click", function (e) {
    e.preventDefault();

    const btn = $(this);

    if (!selectedValue || !selectedPrice) {
      alert("Please select a consultation or class.");
      return;
    }

    // Hide button text, show spinner
    btn.find(".pxgc-btn-text").hide();
    btn.find(".loader").show();
    btn.prop("disabled", true);

    $.ajax({
      url: pxgc_ajax.ajax_url,
      type: "POST",
      data: {
        action: "pxgc_add_to_cart",
        selected_value: selectedValue,
        price: selectedPrice,
      },
      success: function (response) {
        if (response.success && response.data.cart_url) {
          window.location.href = response.data.cart_url;
        } else {
          alert(response.data.message || "Something went wrong.");
          btn.find(".loader").hide();
          btn.find(".pxgc-btn-text").show();
          btn.prop("disabled", false);
        }
      },
      error: function () {
        alert("Request failed.");
        btn.find(".loader").hide();
        btn.find(".pxgc-btn-text").show();
        btn.prop("disabled", false);
      },
    });
  });
});
