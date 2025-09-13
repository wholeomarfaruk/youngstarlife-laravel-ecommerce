// function incrementValue(e) {
//         e.preventDefault();
//         var fieldName = $(e.currentTarget).data('field');
//         var parent = $(e.currentTarget).closest('div');
//         var currentVal = parseInt(parent.find('input[name=' + fieldName + ']').val(), 10);
//     console.log(e.currentTarget)
//         if (!isNaN(currentVal)) {
//             parent.find('input[name=' + fieldName + ']').val(currentVal + 1);
//         } else {
//             parent.find('input[name=' + fieldName + ']').val(0);
//         }
//     }

//     function decrementValue(e) {
//         e.preventDefault();
//         var fieldName = $(e.currentTarget).data('field');
//         var parent = $(e.currentTarget).closest('div');
//         var currentVal = parseInt(parent.find('input[name=' + fieldName + ']').val(), 10);

//         if (!isNaN(currentVal) && currentVal > 1) {
//             parent.find('input[name=' + fieldName + ']').val(currentVal - 1);
//         } else {
//             parent.find('input[name=' + fieldName + ']').val(1);
//         }
//     }

//     $('.input-group').on('click', '.button-plus', function(e) {
//         console.log("clicked")
//         incrementValue(e);
//     });

//     $('.input-group').on('click', '.button-minus', function(e) {
//          console.log("clicked")
//         decrementValue(e);
//     });

