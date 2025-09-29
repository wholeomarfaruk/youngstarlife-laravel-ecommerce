<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\products;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{

    /**
     * Show the categories index page Start.================================================
     */
    public function index()
    {
         $categories = Category::whereNull('parent_id')
        ->with('children') // eager load first level
        ->get();
        return view('admin.category.index', compact('categories'));
    }
    /**
     * Show the categories index page End.================================================
     */

    /**
     * Show the categories add page Start.================================================
     */
    public function add()
    {
        $categories = Category::all();
        return view('admin.category.category-add', compact('categories'));
    }
    /**
     * Show the categories add page end.================================================
     */


    /**
     * Store a newly created resource in storage Start.================================================
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'=> 'required',

        ]);

        // return $request->all();

        // Check if the slug already exists
        $slug = Str::slug($request->name);
        if(Category::where('slug', $slug)->exists()){
            $slug .= '-';
        }

        $category = new Category();
        $category->name = $request->name;
        $category->slug = $slug;
        if($request->has('is_active')) {
            $category->is_active = $request->is_active;
        }

        if($request->hasFile('image')) {
            // Check if the directory exists
            if(!file_exists(public_path('images/category/'))) {
                // Create the directory if it does not exist
                mkdir(public_path('images/category/'), 0777, true);
            }

            // Check if the directory has read and write permissions
            if(!is_writable(public_path('images/category/'))) {
                chmod(public_path('images/category/'), 0777);
            }
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            $image->move(public_path('images/category'), $imageName);
            $category->image = $imageName;
        }
        if($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        if($request->has('description')) {
            $category->description = $request->description;
        }
        $category->save();


        return redirect()->route('admin.categories')->with('success', 'Category added successfully');
    }
    /**
     * Store a newly created resource in storage End.================================================
     */


    /**
     * Show the form for editing the specified category.================================================
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */

    public function edit($id)
    {
        $category = Category::find($id);
        return view('admin.category.category-edit', compact('category'));
    }

    /**
     * Show the form for editing the specified category End.================================================
     */


    // Update the specified category in storage Start.================================================

    // @param  \App\Http\Requests\CategoryRequest  $request
    // @param  int  $id
    // @return \Illuminate\Http\Response

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'=> 'required',
            'status'=> 'required',
        ]);
        $category = Category::find($id);

        if (!$category) {
            return redirect()->back()->with('error', 'Category not found');
        }

        // Check if the slug already exists
        $existingCategory = Category::where('slug', $request->slug)->where('id', '!=', $id)->first();
        if ($existingCategory) {
            $slug = $request->slug . '-' . time();
        }
        // Check if the slug already exists
        $slug = Str::slug($request->name);
        if(Category::where('slug', $slug)->where('id', '!=', $id)->exists()){
            $slug .= '-';
        }

        $category->update([
            'status' => $request->status ?? 1,
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return redirect()->route('admin.categories.list')->with('success', 'Category updated successfully');

    }
    // Update the specified category in storage End.================================================

    // Remove the specified category from storage Start.================================================
    public function delete($id)
    {

        $category = Category::find($id);

        if (!$category) {
            return redirect()->back()->with('error', 'Category not found');
        }
     if($category->image){
            if(file_exists(public_path('images/category/' . $category->image))){
                unlink(public_path('images/category/' . $category->image));
            }
        }
        $category->delete();



        return redirect()->route('admin.categories.list')->with('success', 'Category deleted successfully');

    }
    // Remove the specified category from storage End.================================================
    public function manageProducts($id){

        $category = Category::find($id);
        $Categoryproducts = $category->products ?? collect();
        $ids = $Categoryproducts?->pluck('id')->toArray() ?? [];
        $products = products::all()->except($ids);
        return view('admin.category.manage-products', compact('category', 'products', 'Categoryproducts'));
    }
    public function assignProducts(Request $request, $id){

        $category = Category::find($id);
        $category->products()->attach($request->products);
        return redirect()->back()->with('status', 'Product added successfully');
    }
    public function unassignProducts(Request $request, $id){
        $category = Category::find($id);
        $category->products()->detach($request->products);
        return redirect()->back()->with('status', 'Product removed successfully');
    }



}

