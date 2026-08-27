function formatStudentNumber(input){
    let val = input.value.replace(/[^0-9]/g, '');
    if(val.length > 9) val = val.substring(0, 9);
    if(val.length > 4) val = val.substring(0, 4) + '-' + val.substring(4);
    input.value = val;
}
